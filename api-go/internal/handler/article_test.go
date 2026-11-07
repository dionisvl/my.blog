package handler

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/go-chi/chi/v5"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"api-go/internal/model"
	"api-go/internal/repository"
	"api-go/internal/repository/testhelper"
)

type fakeArticleRepo struct {
	resolution *model.ArticleResolution
	locale     *model.PublishedLocale
	err        error

	resolveCalls   int
	localeCalls    int
	sitemapLocales []model.PublishedSitemapLocale
	sitemapErr     error
}

func (f *fakeArticleRepo) FindPublishedSitemapLocales(ctx context.Context) ([]model.PublishedSitemapLocale, error) {
	return f.sitemapLocales, f.sitemapErr
}

func (f *fakeArticleRepo) ResolveBySlug(ctx context.Context, slug string) (*model.ArticleResolution, error) {
	f.resolveCalls++
	return f.resolution, f.err
}

func (f *fakeArticleRepo) FindPublishedLocale(ctx context.Context, slug, locale string) (*model.PublishedLocale, error) {
	f.localeCalls++
	return f.locale, f.err
}

func newArticleRouter(posts *fakePostRepo, articles *fakeArticleRepo) *chi.Mux {
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	h := NewArticleHandler(posts, articles, &fakeCategoryRepo{}, &fakeAphorismRepo{}, logger)

	router := chi.NewRouter()
	router.Get("/api/post/{slug}/resolve", h.Resolve)
	router.Get("/api/post/{slug}/locale/{locale}", h.Locale)
	return router
}

func get(router http.Handler, path string, cookies ...*http.Cookie) *httptest.ResponseRecorder {
	req := httptest.NewRequest(http.MethodGet, path, nil)
	for _, c := range cookies {
		req.AddCookie(c)
	}
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)
	return rec
}

func TestArticleResolve_IsSideEffectFree(t *testing.T) {
	primary := "ru"
	articles := &fakeArticleRepo{resolution: &model.ArticleResolution{
		ContractVersion: model.ContractVersion,
		Slug:            "cards",
		RouteMode:       model.RouteModeNeutral,
		PrimaryLocale:   &primary,
		Locales: []model.LocaleLink{
			{Locale: "ru", URL: "/ru/post/cards", Title: "Карты", PublishedAt: time.Date(2026, 10, 29, 10, 0, 0, 0, time.UTC)},
		},
	}}
	posts := &fakePostRepo{post: &model.Post{ID: 7, Slug: "cards", RouteMode: model.RouteModeNeutral}}

	rec := get(newArticleRouter(posts, articles), "/api/post/cards/resolve")

	require.Equal(t, http.StatusOK, rec.Code)
	assert.Equal(t, "private, no-store", rec.Header().Get("Cache-Control"))
	assert.Empty(t, rec.Result().Cookies(), "the resolver must not set cookies")
	assert.Zero(t, posts.incrementCalls, "the resolver must not count views")
	assert.Equal(t, 1, articles.resolveCalls)

	var body map[string]json.RawMessage
	require.NoError(t, json.Unmarshal(rec.Body.Bytes(), &body))
	for _, key := range []string{"contract_version", "slug", "route_mode", "original_locale", "primary_locale", "canonical_path", "locales", "x_default_locale"} {
		assert.Contains(t, body, key)
	}
	assert.JSONEq(t, `null`, string(body["original_locale"]))
	assert.JSONEq(t, `"ru"`, string(body["primary_locale"]))
	assert.JSONEq(t, `"neutral"`, string(body["route_mode"]))
}

func TestArticleAPI_ErrorResponses(t *testing.T) {
	published := &model.PublishedLocale{Locale: "en", SchemaVersion: 1, Title: "Title"}
	neutral := &model.Post{ID: 7, Slug: "cards", RouteMode: model.RouteModeNeutral}
	legacy := &model.Post{ID: 1, Slug: "cards", RouteMode: model.RouteModeLegacy}

	tests := []struct {
		name         string
		path         string
		post         *model.Post
		articles     *fakeArticleRepo
		wantStatus   int
		wantBody     string // exact JSON; empty skips the check
		mustNotLeak  string // text that must not appear in the body
		noLocaleCall bool
	}{
		{"resolve: unknown or unpublished slug is 404", "/api/post/nope/resolve", nil, &fakeArticleRepo{err: sql.ErrNoRows}, http.StatusNotFound, `{"error":"post not found"}`, "", false},
		{"resolve: repository failure is a generic 500", "/api/post/x/resolve", nil, &fakeArticleRepo{err: errors.New("pq: secret details")}, http.StatusInternalServerError, "", "secret", false},
		{"locale: unsupported prefix is 404 without touching the database", "/api/post/cards/locale/de", neutral, &fakeArticleRepo{locale: published}, http.StatusNotFound, "", "", true},
		{"locale: a locale that is not published is 404", "/api/post/cards/locale/ru", neutral, &fakeArticleRepo{err: sql.ErrNoRows}, http.StatusNotFound, "", "document", false},
		{"locale: a legacy post has no modular locales", "/api/post/cards/locale/en", legacy, &fakeArticleRepo{locale: published}, http.StatusNotFound, "", "", false},
		{"locale: repository failure is a generic 500", "/api/post/cards/locale/en", neutral, &fakeArticleRepo{err: errors.New("boom: password=hunter2")}, http.StatusInternalServerError, "", "hunter2", false},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			rec := get(newArticleRouter(&fakePostRepo{post: tc.post}, tc.articles), tc.path)

			assert.Equal(t, tc.wantStatus, rec.Code)
			assert.Empty(t, rec.Result().Cookies())
			if tc.wantBody != "" {
				assert.JSONEq(t, tc.wantBody, rec.Body.String())
			}
			if tc.mustNotLeak != "" {
				assert.NotContains(t, rec.Body.String(), tc.mustNotLeak)
			}
			if tc.noLocaleCall {
				assert.Zero(t, tc.articles.localeCalls)
			}
		})
	}
}

func TestArticleLocale_ServesOnlyWhatTheRepositoryPublishes(t *testing.T) {
	document := json.RawMessage(`{"schema_version":1,"locale":"en","blocks":[{"id":"body","type":"richText","props":{"html":"<p>Hi</p>"}}]}`)
	published := &model.PublishedLocale{
		Locale:        "en",
		SchemaVersion: 1,
		PublishedAt:   time.Date(2026, 10, 29, 10, 0, 0, 0, time.UTC),
		Title:         "Title",
		Summary:       "Summary",
		Document:      document,
	}
	post := &model.Post{
		ID:        7,
		Slug:      "cards",
		RouteMode: model.RouteModeNeutral,
		Locales:   []model.LocaleLink{{Locale: "en", URL: "/en/post/cards", Title: "Title"}},
	}

	t.Run("success has the documented shape and no side effects", func(t *testing.T) {
		posts := &fakePostRepo{post: post}
		rec := get(newArticleRouter(posts, &fakeArticleRepo{locale: published}), "/api/post/cards/locale/en")

		require.Equal(t, http.StatusOK, rec.Code)
		assert.Empty(t, rec.Result().Cookies())
		assert.Zero(t, posts.incrementCalls)

		var body struct {
			ContractVersion int `json:"contract_version"`
			Post            struct {
				ID        int    `json:"id"`
				RouteMode string `json:"route_mode"`
			} `json:"post"`
			Article struct {
				Locale   string          `json:"locale"`
				Title    string          `json:"title"`
				Document json.RawMessage `json:"document"`
			} `json:"article"`
			Locales       []model.LocaleLink `json:"locales"`
			FeaturedPosts []model.Post       `json:"featured_posts"`
			RecentPosts   []model.Post       `json:"recent_posts"`
			Categories    []json.RawMessage  `json:"categories"`
		}
		require.NoError(t, json.Unmarshal(rec.Body.Bytes(), &body))

		assert.Equal(t, 1, body.ContractVersion)
		assert.Equal(t, 7, body.Post.ID)
		assert.Equal(t, "neutral", body.Post.RouteMode)
		assert.Equal(t, "en", body.Article.Locale)
		assert.Equal(t, "Title", body.Article.Title)
		assert.JSONEq(t, string(document), string(body.Article.Document), "the document is passed through verbatim")
		require.Len(t, body.Locales, 1)
		assert.NotNil(t, body.FeaturedPosts)
		assert.NotNil(t, body.RecentPosts)
		assert.NotNil(t, body.Categories)
	})
}

func TestArticleLocale_ServesClassifiedLegacyTranslation(t *testing.T) {
	original := "ru"
	post := &model.Post{ID: 7, Slug: "legacy", RouteMode: model.RouteModeLegacy, OriginalLocale: &original}
	articles := &fakeArticleRepo{locale: &model.PublishedLocale{
		Locale: "en", Title: "Translated", Document: json.RawMessage(`{"schema_version":1,"locale":"en","blocks":[]}`),
	}}
	posts := &fakePostRepo{post: post}
	rec := get(newArticleRouter(posts, articles), "/api/post/legacy/locale/en")

	require.Equal(t, http.StatusOK, rec.Code)
	assert.Contains(t, rec.Body.String(), `"route_mode":"legacy_canonical"`)
	assert.Contains(t, rec.Body.String(), `"title":"Translated"`)
	assert.Zero(t, posts.incrementCalls)
}

func TestArticleAPI_GoldenResponses(t *testing.T) {
	at := time.Date(2026, 10, 29, 10, 0, 0, 0, time.UTC)
	ru, en := "ru", "en"
	links := []model.LocaleLink{
		{Locale: ru, URL: "/ru/post/cards", Title: "Карты", PublishedAt: at},
		{Locale: en, URL: "/en/post/cards", Title: "Cards", PublishedAt: at},
	}
	resolution := &model.ArticleResolution{
		ContractVersion: 1, Slug: "cards", RouteMode: model.RouteModeNeutral,
		PrimaryLocale: &ru, Locales: links, XDefaultLocale: &en,
	}
	post := &model.Post{
		ID: 7, Title: "Карты", Slug: "cards", RouteMode: model.RouteModeNeutral,
		CreatedAt: at, UpdatedAt: at, Locales: links,
	}
	article := &model.PublishedLocale{
		Locale: en, SchemaVersion: 1,
		PublishedAt: at, Title: "Cards", Summary: "Plain summary",
		Document: json.RawMessage(`{"schema_version":1,"locale":"en","blocks":[{"id":"body","type":"richText","props":{"html":"<p>Hi</p>"}}]}`),
	}
	router := newArticleRouter(&fakePostRepo{post: post}, &fakeArticleRepo{resolution: resolution, locale: article})

	for _, tc := range []struct{ path, fixture string }{
		{"/api/post/cards/resolve", "resolve-bilingual.json"},
		{"/api/post/cards/locale/en", "locale-en.json"},
	} {
		t.Run(tc.fixture, func(t *testing.T) {
			golden, err := os.ReadFile(filepath.Join("testdata", tc.fixture))
			require.NoError(t, err)
			response := get(router, tc.path)
			require.Equal(t, http.StatusOK, response.Code)
			assert.JSONEq(t, string(golden), response.Body.String())
		})
	}
}

// seedNeutralAnchor publishes a neutral anchor for one locale the way ArticlePublisher does: the locale row
// first, then the visibility flip.
func seedNeutralAnchor(t *testing.T, tdb *testhelper.TestDB, userID int, slug, locale, title string) int {
	t.Helper()
	ctx := context.Background()

	var postID int
	require.NoError(t, tdb.SQLDB().QueryRowContext(ctx,
		`INSERT INTO posts (title, slug, content, description, status, is_featured, views_count, user_id, route_mode, primary_locale)
		 VALUES ($1, $2, '', '', TRUE, FALSE, 0, $3, 'neutral', $4) RETURNING id`,
		title, slug, userID, locale,
	).Scan(&postID))
	_, err := tdb.SQLDB().ExecContext(ctx,
		`INSERT INTO post_locales (post_id, locale, schema_version, title, summary, search_text, document, published_at)
		 VALUES ($1, $2, 1, $3, 'summary', 'text', $4::jsonb, NOW())`,
		postID, locale, title, `{"schema_version":1,"locale":"`+locale+`","blocks":[]}`,
	)
	require.NoError(t, err)
	_, err = tdb.SQLDB().ExecContext(ctx, `UPDATE posts SET status = FALSE WHERE id = $1`, postID)
	require.NoError(t, err)

	return postID
}

// seedLegacyEnglishDuplicate adds a later legacy post with the same slug that owns a published EN translation.
// The partial unique index covers only neutral anchors, so the duplicate slug is allowed.
func seedLegacyEnglishDuplicate(t *testing.T, tdb *testhelper.TestDB, userID int, slug, title string) int {
	t.Helper()
	ctx := context.Background()

	var postID int
	require.NoError(t, tdb.SQLDB().QueryRowContext(ctx,
		`INSERT INTO posts (title, slug, content, status, is_featured, views, views_count, user_id, created_at, updated_at)
		 VALUES ($1, $2, '', FALSE, FALSE, 0, 0, $3, NOW(), NOW()) RETURNING id`,
		title, slug, userID,
	).Scan(&postID))
	_, err := tdb.SQLDB().ExecContext(ctx, `UPDATE posts SET original_locale = 'ru' WHERE id = $1`, postID)
	require.NoError(t, err)
	_, err = tdb.SQLDB().ExecContext(ctx,
		`INSERT INTO post_locales (post_id, locale, schema_version, title, summary, search_text, document, published_at)
		 VALUES ($1, 'en', 1, $2, 'summary', 'text', '{"schema_version":1,"locale":"en","blocks":[]}'::jsonb, NOW())`,
		postID, title,
	)
	require.NoError(t, err)

	return postID
}

func TestArticleLocale_DuplicateSlugNeverMixesAnchors(t *testing.T) {
	tdb := testhelper.NewBareTestDB(t)
	tdb.ApplyStatements(t, testhelper.BaselineStatements(t))
	tdb.ApplyScript(t, testhelper.MigrationUp)
	ctx := context.Background()

	var userID int
	require.NoError(t, tdb.SQLDB().QueryRowContext(ctx,
		`INSERT INTO users (name, email, is_admin) VALUES ('Author', 'handler-dup@test.com', FALSE) RETURNING id`).Scan(&userID))

	// The lowest id is the resolved anchor (RU only); the later duplicate owns the EN translation.
	anchorID := seedNeutralAnchor(t, tdb, userID, "dup-slug", "ru", "RU anchor")
	seedLegacyEnglishDuplicate(t, tdb, userID, "dup-slug", "EN duplicate")

	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	h := NewArticleHandler(
		repository.NewPostRepository(tdb.DB),
		repository.NewArticleRepository(tdb.DB),
		// The baseline has no aphorism table; the sidebar fakes keep the test on the anchor rule.
		&fakeCategoryRepo{},
		&fakeAphorismRepo{},
		logger,
	)
	router := chi.NewRouter()
	router.Get("/api/post/{slug}/locale/{locale}", h.Locale)

	t.Run("a locale the resolved anchor does not publish is 404, not the duplicate's translation", func(t *testing.T) {
		rec := get(router, "/api/post/dup-slug/locale/en")

		assert.Equal(t, http.StatusNotFound, rec.Code)
		assert.NotContains(t, rec.Body.String(), "EN duplicate")
	})

	t.Run("the resolved anchor serves its own post and its own locale", func(t *testing.T) {
		rec := get(router, "/api/post/dup-slug/locale/ru")

		require.Equal(t, http.StatusOK, rec.Code)
		var body struct {
			Post struct {
				ID int `json:"id"`
			} `json:"post"`
			Article struct {
				Title string `json:"title"`
			} `json:"article"`
		}
		require.NoError(t, json.Unmarshal(rec.Body.Bytes(), &body))
		assert.Equal(t, anchorID, body.Post.ID)
		assert.Equal(t, "RU anchor", body.Article.Title)
	})
}
