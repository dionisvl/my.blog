package repository_test

import (
	"context"
	"database/sql"
	"encoding/json"
	"os"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
	"github.com/uptrace/bun"
	"github.com/uptrace/bun/dialect/pgdialect"
	"github.com/uptrace/bun/driver/pgdriver"

	"api-go/internal/model"
	"api-go/internal/repository"
	"api-go/internal/repository/testhelper"
)

func truncateAll(t *testing.T, tdb *testhelper.TestDB) {
	t.Helper()
	tdb.Truncate(t, "post_tags", "posts_likes", "comments", "posts", "users", "categories")
}

func TestArticles_DraftsNeverLeakIntoAnyPublicQuery(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	truncateAll(t, tdb)

	ctx := context.Background()
	userID := insertUser(t, tdb, "drafts@test.com")
	catID := insertCategory(t, tdb, "Cards", "cards")
	anchorID := insertAnchor(t, tdb, "draft-only", "ru", userID, &catID)
	insertLocale(t, tdb, anchorID, "ru", "Secret draft title", "secret summary", "needle-draft-term", articleDoc("ru"))
	setLocalePublished(t, tdb, anchorID, "ru", false)

	posts := repository.NewPostRepository(tdb.DB)
	articles := repository.NewArticleRepository(tdb.DB)
	categories := repository.NewCategoryRepository(tdb.DB)

	assertHidden := func(t *testing.T) {
		t.Helper()

		_, err := posts.FindPublishedBySlug(ctx, "draft-only")
		assert.ErrorIs(t, err, sql.ErrNoRows, "FindPublishedBySlug")

		list, total, err := posts.FindPublishedPaginated(ctx, 1, 10)
		require.NoError(t, err)
		assert.Zero(t, total)
		assert.Empty(t, list)

		byCategory, total, err := posts.FindPublishedByCategorySlugPaginated(ctx, "cards", 1, 10)
		require.NoError(t, err)
		assert.Zero(t, total)
		assert.Empty(t, byCategory)

		recent, err := posts.FindRecentPublished(ctx, 10)
		require.NoError(t, err)
		assert.Empty(t, recent)

		for _, term := range []string{"needle-draft-term", "Secret draft title", "Working draft-only"} {
			found, err := posts.SearchPublished(ctx, term, 10)
			require.NoError(t, err)
			assert.Empty(t, found, "search for %q", term)
		}

		_, err = articles.ResolveBySlug(ctx, "draft-only")
		assert.ErrorIs(t, err, sql.ErrNoRows, "ResolveBySlug")

		_, err = articles.FindPublishedLocale(ctx, "draft-only", "ru")
		assert.ErrorIs(t, err, sql.ErrNoRows, "FindPublishedLocale")

		counts, err := categories.FindWithPostCounts(ctx)
		require.NoError(t, err)
		assert.Empty(t, counts, "a category with only hidden anchors must not be listed")
	}

	t.Run("explicit draft status", assertHidden)

	t.Run("status flipped to the legacy default (published) but nothing published", func(t *testing.T) {
		require.NoError(t, execSQL(tdb, `UPDATE posts SET status = FALSE WHERE id = $1`, anchorID))
		assertHidden(t)
	})
}

func TestArticles_PublishedLocalesAreVisibleSearchableAndSideEffectFree(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	truncateAll(t, tdb)

	ctx := context.Background()
	userID := insertUser(t, tdb, "published@test.com")
	catID := insertCategory(t, tdb, "Cards", "cards")
	anchorID := insertAnchor(t, tdb, "two-locales", "ru", userID, &catID)

	// Russian is only a draft; English is published.
	insertLocale(t, tdb, anchorID, "ru", "Русский черновик", "ru draft summary", "needle-ru-draft", articleDoc("ru"))
	setLocalePublished(t, tdb, anchorID, "ru", false)
	publishLocale(t, tdb, anchorID, "en", "English <b>title</b>", "Sum <script>alert(1)</script> & more", "needle-en-text")

	posts := repository.NewPostRepository(tdb.DB)
	articles := repository.NewArticleRepository(tdb.DB)

	t.Run("detail", func(t *testing.T) {
		post, err := posts.FindPublishedBySlug(ctx, "two-locales")
		require.NoError(t, err)
		assert.True(t, post.IsModular())
		assert.Equal(t, "English <b>title</b>", post.Title, "plain text title, escaped by the renderer")
		require.NotNil(t, post.Description)
		assert.Equal(t, "Sum &lt;script&gt;alert(1)&lt;/script&gt; &amp; more", *post.Description, "plain card text is HTML-escaped")
		require.Len(t, post.Locales, 1)
		assert.Equal(t, "en", post.Locales[0].Locale)
		assert.Equal(t, "/en/post/two-locales", post.Locales[0].URL)
	})

	t.Run("list and search show one card", func(t *testing.T) {
		list, total, err := posts.FindPublishedPaginated(ctx, 1, 10)
		require.NoError(t, err)
		assert.Equal(t, 1, total)
		require.Len(t, list, 1)
		require.Len(t, list[0].Locales, 1)

		found, err := posts.SearchPublished(ctx, "needle-en-text", 10)
		require.NoError(t, err)
		assert.Len(t, found, 1, "published locale search_text is searchable")

		found, err = posts.SearchPublished(ctx, "needle-ru-draft", 10)
		require.NoError(t, err)
		assert.Empty(t, found, "draft locale text is not searchable")

		counts, err := repository.NewCategoryRepository(tdb.DB).FindWithPostCounts(ctx)
		require.NoError(t, err)
		require.Len(t, counts, 1)
		assert.Equal(t, 1, counts[0].PostsCount)
	})

	t.Run("resolver", func(t *testing.T) {
		resolution, err := articles.ResolveBySlug(ctx, "two-locales")
		require.NoError(t, err)
		assert.Equal(t, model.ContractVersion, resolution.ContractVersion)
		assert.Equal(t, model.RouteModeNeutral, resolution.RouteMode)
		require.NotNil(t, resolution.PrimaryLocale)
		assert.Equal(t, "ru", *resolution.PrimaryLocale)
		assert.Nil(t, resolution.CanonicalPath)
		assert.Nil(t, resolution.XDefaultLocale, "x-default only when both locales are published")
		require.Len(t, resolution.Locales, 1)
		assert.Equal(t, "en", resolution.Locales[0].Locale)

		var views int
		require.NoError(t, tdb.SQLDB().QueryRowContext(ctx, `SELECT views_count FROM posts WHERE id = $1`, anchorID).Scan(&views))
		assert.Zero(t, views, "the resolver must not count views")
	})

	t.Run("published locale content", func(t *testing.T) {
		_, err := articles.FindPublishedLocale(ctx, "two-locales", "ru")
		assert.ErrorIs(t, err, sql.ErrNoRows, "an unpublished locale has no public content")

		en, err := articles.FindPublishedLocale(ctx, "two-locales", "en")
		require.NoError(t, err)
		assert.Equal(t, "en", en.Locale)
		assert.Equal(t, 1, en.SchemaVersion)
		assert.Equal(t, "English <b>title</b>", en.Title)
		assert.Equal(t, "Sum <script>alert(1)</script> & more", en.Summary, "the API serves plain text; the renderer escapes")
		assert.Nil(t, en.MetaTitle)
		assert.JSONEq(t, articleDoc("en"), string(en.Document))
	})

	t.Run("publishing the primary locale switches the card, then unpublishing hides it again", func(t *testing.T) {
		// Publish the former Russian draft.
		setLocalePublished(t, tdb, anchorID, "ru", true)

		post, err := posts.FindPublishedBySlug(ctx, "two-locales")
		require.NoError(t, err)
		assert.Equal(t, "Русский черновик", post.Title, "the primary locale provides the card")
		require.Len(t, post.Locales, 2)

		resolution, err := articles.ResolveBySlug(ctx, "two-locales")
		require.NoError(t, err)
		require.Len(t, resolution.Locales, 2)
		require.NotNil(t, resolution.XDefaultLocale)
		assert.Equal(t, "en", *resolution.XDefaultLocale)

		// Unpublishing hides the locale and its text again.
		setLocalePublished(t, tdb, anchorID, "ru", false)
		_, err = articles.FindPublishedLocale(ctx, "two-locales", "ru")
		assert.ErrorIs(t, err, sql.ErrNoRows)

		found, err := posts.SearchPublished(ctx, "needle-ru-draft", 10)
		require.NoError(t, err)
		assert.Empty(t, found)
	})
}

func TestArticles_LegacyPostsStayCompatible(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	truncateAll(t, tdb)

	ctx := context.Background()
	userID := insertUser(t, tdb, "legacy@test.com")
	legacyID := insertPost(t, tdb, "Legacy post", "legacy-post", false, userID)
	insertPost(t, tdb, "Legacy draft", "legacy-draft", true, userID)

	posts := repository.NewPostRepository(tdb.DB)
	articles := repository.NewArticleRepository(tdb.DB)

	t.Run("resolver", func(t *testing.T) {
		resolution, err := articles.ResolveBySlug(ctx, "legacy-post")
		require.NoError(t, err)
		assert.Equal(t, model.RouteModeLegacy, resolution.RouteMode)
		require.NotNil(t, resolution.CanonicalPath)
		assert.Equal(t, "/post/legacy-post", *resolution.CanonicalPath)
		assert.Nil(t, resolution.OriginalLocale, "unclassified legacy posts keep NULL")
		assert.NotNil(t, resolution.Locales)
		assert.Empty(t, resolution.Locales)

		_, err = articles.ResolveBySlug(ctx, "legacy-draft")
		assert.ErrorIs(t, err, sql.ErrNoRows)

		_, err = articles.FindPublishedLocale(ctx, "legacy-post", "en")
		assert.ErrorIs(t, err, sql.ErrNoRows, "legacy posts have no modular locales")
	})

	t.Run("json keeps every pre-existing key and only adds route_mode", func(t *testing.T) {
		post, err := posts.FindPublishedBySlug(ctx, "legacy-post")
		require.NoError(t, err)

		raw, err := json.Marshal(post)
		require.NoError(t, err)

		var keys map[string]json.RawMessage
		require.NoError(t, json.Unmarshal(raw, &keys))

		for _, key := range []string{"id", "title", "slug", "is_featured", "views_count", "created_at", "updated_at", "image_url", "likes_count", "route_mode"} {
			assert.Contains(t, keys, key)
		}
		for _, key := range []string{"locales", "primary_locale", "original_locale"} {
			assert.NotContains(t, keys, key, "optional modular keys are omitted for unclassified legacy posts")
		}
		assert.JSONEq(t, `"legacy_canonical"`, string(keys["route_mode"]))
	})

	t.Run("classified original locale is exposed", func(t *testing.T) {
		require.NoError(t, execSQL(tdb, `UPDATE posts SET original_locale = 'ru' WHERE id = $1`, legacyID))

		resolution, err := articles.ResolveBySlug(ctx, "legacy-post")
		require.NoError(t, err)
		require.NotNil(t, resolution.OriginalLocale)
		assert.Equal(t, "ru", *resolution.OriginalLocale)

		post, err := posts.FindPublishedBySlug(ctx, "legacy-post")
		require.NoError(t, err)
		require.NotNil(t, post.OriginalLocale)
		assert.Equal(t, "ru", *post.OriginalLocale)
	})
}

func TestArticles_ClassifiedLegacyTranslationIsPublicOnlyWhenPublished(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	truncateAll(t, tdb)

	ctx := context.Background()
	userID := insertUser(t, tdb, "legacy-translation@test.com")
	postID := insertPost(t, tdb, "Original", "legacy-translated", false, userID)
	require.NoError(t, execSQL(tdb, `UPDATE posts SET original_locale = 'ru' WHERE id = $1`, postID))
	insertLocale(t, tdb, postID, "en", "Translation", "English summary", "translated text", articleDoc("en"))
	setLocalePublished(t, tdb, postID, "en", false)
	articles := repository.NewArticleRepository(tdb.DB)

	_, err := articles.FindPublishedLocale(ctx, "legacy-translated", "en")
	assert.ErrorIs(t, err, sql.ErrNoRows, "a draft translation must not be exposed")

	setLocalePublished(t, tdb, postID, "en", true)
	translated, err := articles.FindPublishedLocale(ctx, "legacy-translated", "en")
	require.NoError(t, err)
	assert.Equal(t, "Translation", translated.Title)

	_, err = articles.FindPublishedLocale(ctx, "legacy-translated", "ru")
	assert.ErrorIs(t, err, sql.ErrNoRows, "the legacy original has no modular locale")
}

func TestArticles_ClassifiedLegacyResolverIncludesOriginalAndPublishedTranslation(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	truncateAll(t, tdb)

	ctx := context.Background()
	userID := insertUser(t, tdb, "legacy-resolver@test.com")
	postID := insertPost(t, tdb, "Original", "legacy-bilingual", false, userID)
	require.NoError(t, execSQL(tdb, `UPDATE posts SET original_locale = 'ru' WHERE id = $1`, postID))
	insertLocale(t, tdb, postID, "en", "Translation", "English summary", "translated text", articleDoc("en"))
	setLocalePublished(t, tdb, postID, "en", true)

	resolution, err := repository.NewArticleRepository(tdb.DB).ResolveBySlug(ctx, "legacy-bilingual")
	require.NoError(t, err)
	require.NotNil(t, resolution.CanonicalPath)
	assert.Equal(t, "/post/legacy-bilingual", *resolution.CanonicalPath)
	require.Len(t, resolution.Locales, 2)
	assert.Equal(t, "ru", resolution.Locales[0].Locale)
	assert.Equal(t, "/post/legacy-bilingual", resolution.Locales[0].URL)
	assert.Equal(t, "en", resolution.Locales[1].Locale)
	assert.Equal(t, "/en/post/legacy-bilingual", resolution.Locales[1].URL)

	post, err := repository.NewPostRepository(tdb.DB).FindPublishedBySlug(ctx, "legacy-bilingual")
	require.NoError(t, err)
	assert.Equal(t, "Original", post.Title)
	require.Len(t, post.Locales, 2)
	assert.Equal(t, resolution.Locales[0].URL, post.Locales[0].URL)
	assert.Equal(t, resolution.Locales[1].URL, post.Locales[1].URL)
}

func TestArticlesFindPublishedSitemapLocalesOnlyPublishedNeutralRows(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	truncateAll(t, tdb)
	ctx := context.Background()
	userID := insertUser(t, tdb, "sitemap@test.com")

	neutralID := insertAnchor(t, tdb, "neutral-both", "ru", userID, nil)
	publishLocale(t, tdb, neutralID, "ru", "Русский", "summary", "search ru")

	draftID := insertAnchor(t, tdb, "neutral-draft", "en", userID, nil)
	insertLocale(t, tdb, draftID, "en", "Draft", "summary", "search", articleDoc("en"))

	legacyID := insertPost(t, tdb, "Legacy", "legacy-sitemap", false, userID)
	require.NoError(t, execSQL(tdb, `UPDATE posts SET original_locale = 'ru' WHERE id = $1`, legacyID))
	insertLocale(t, tdb, legacyID, "en", "Legacy translation", "summary", "search", articleDoc("en"))
	setLocalePublished(t, tdb, legacyID, "en", true)

	articles := repository.NewArticleRepository(tdb.DB)
	locales, err := articles.FindPublishedSitemapLocales(ctx)
	require.NoError(t, err)
	require.Len(t, locales, 1)
	assert.Equal(t, "neutral-both", locales[0].Slug)
	assert.Equal(t, "ru", locales[0].Locale)

	publishLocale(t, tdb, neutralID, "en", "English", "summary", "search en")
	locales, err = articles.FindPublishedSitemapLocales(ctx)
	require.NoError(t, err)
	require.Len(t, locales, 2)
	assert.Equal(t, "neutral-both", locales[0].Slug)
	assert.Equal(t, "en", locales[0].Locale)
	assert.Equal(t, "neutral-both", locales[1].Slug)
	assert.Equal(t, "ru", locales[1].Locale)

	setLocalePublished(t, tdb, neutralID, "en", false)
	locales, err = articles.FindPublishedSitemapLocales(ctx)
	require.NoError(t, err)
	require.Len(t, locales, 1)
	assert.Equal(t, "neutral-both", locales[0].Slug)
	assert.Equal(t, "ru", locales[0].Locale)
}

func TestArticles_ConsumePhpPublishedLocale(t *testing.T) {
	dsn := os.Getenv("ARTICLE_PHP_PUBLISHED_DSN")
	if dsn == "" {
		t.Skip("requires the PostgreSQL fixture seeded by ArticlePublisherTest::testPublishMakesTheAnchorVisibleWithEscapedCardFields")
	}

	db := bun.NewDB(sql.OpenDB(pgdriver.NewConnector(pgdriver.WithDSN(dsn))), pgdialect.New())
	defer db.Close()
	db.RegisterModel((*model.PostTag)(nil))
	ctx := context.Background()

	article, err := repository.NewArticleRepository(db).FindPublishedLocale(ctx, "modular-publish", "en")
	require.NoError(t, err)
	assert.Equal(t, "Card <b>title</b>", article.Title)
	assert.Equal(t, "<script>alert(1)</script> & more", article.Summary)
	assert.Equal(t, 1, article.SchemaVersion)

	post, err := repository.NewPostRepository(db).FindPublishedBySlug(ctx, "modular-publish")
	require.NoError(t, err)
	assert.Equal(t, "Card <b>title</b>", post.Title)
	require.NotNil(t, post.Description)
	assert.Equal(t, "&lt;script&gt;alert(1)&lt;/script&gt; &amp; more", *post.Description)
	assert.Len(t, post.Locales, 1)

	resolution, err := repository.NewArticleRepository(db).ResolveBySlug(ctx, "modular-publish")
	require.NoError(t, err)
	require.Len(t, resolution.Locales, 1)
	assert.Equal(t, "/en/post/modular-publish", resolution.Locales[0].URL)
}

// newDuplicatePermittingDB restores the production baseline, which has NO unique index on posts.slug,
// and applies the migration, so several posts can share one legacy slug as they can in production.
func newDuplicatePermittingDB(t *testing.T) *testhelper.TestDB {
	t.Helper()

	tdb := testhelper.NewBareTestDB(t)
	tdb.ApplyStatements(t, testhelper.BaselineStatements(t))
	tdb.ApplyScript(t, testhelper.MigrationUp)
	return tdb
}

// insertBaselineUser inserts an author the way the production schema requires: is_admin is NOT NULL there.
func insertBaselineUser(t *testing.T, tdb *testhelper.TestDB, email string) int {
	t.Helper()

	var id int
	err := tdb.SQLDB().QueryRowContext(context.Background(),
		`INSERT INTO users (name, email, is_admin) VALUES ('Author', $1, FALSE) RETURNING id`, email,
	).Scan(&id)
	require.NoError(t, err)
	return id
}

// insertLegacyDuplicate inserts a legacy post that shares a slug with another post, with a classified
// original locale. The partial unique index only covers neutral anchors, so this is allowed.
func insertLegacyDuplicate(t *testing.T, tdb *testhelper.TestDB, userID int, slug, originalLocale string) int {
	t.Helper()

	var id int
	err := tdb.SQLDB().QueryRowContext(context.Background(),
		`INSERT INTO posts (title, slug, content, status, is_featured, views, views_count, user_id, created_at, updated_at)
		 VALUES ($1, $2, '', FALSE, FALSE, 0, 0, $3, NOW(), NOW()) RETURNING id`,
		"Legacy "+slug, slug, userID,
	).Scan(&id)
	require.NoError(t, err)
	require.NoError(t, execSQL(tdb, `UPDATE posts SET original_locale = $1 WHERE id = $2`, originalLocale, id))
	return id
}

func TestArticles_DuplicateSlugResolvesToOneAnchor(t *testing.T) {
	tests := []struct {
		name  string
		email string
		slug  string
		// setup returns the id of the anchor that must own the slug.
		setup func(t *testing.T, tdb *testhelper.TestDB, userID int) int
		// wantRoute is checked together with the resolver's locales; empty skips the resolver.
		wantRoute     string
		wantResolved  []string
		wantLocales   map[string]string // locale -> served title; "" means sql.ErrNoRows
		wantPostTitle string
		wantPostLocs  []string
	}{
		{
			name: "neutral anchor keeps its own locale, a later legacy duplicate cannot lend EN", email: "dup@test.com", slug: "dup-slug",
			setup: func(t *testing.T, tdb *testhelper.TestDB, userID int) int {
				anchorID := insertAnchor(t, tdb, "dup-slug", "ru", userID, nil)
				publishLocale(t, tdb, anchorID, "ru", "RU anchor", "ru summary", "needle-ru-anchor")
				duplicateID := insertLegacyDuplicate(t, tdb, userID, "dup-slug", "ru")
				insertLocale(t, tdb, duplicateID, "en", "EN duplicate", "en summary", "needle-en-duplicate", articleDoc("en"))
				setLocalePublished(t, tdb, duplicateID, "en", true)
				return anchorID
			},
			wantRoute: model.RouteModeNeutral, wantResolved: []string{"ru"},
			wantLocales:   map[string]string{"ru": "RU anchor", "en": ""},
			wantPostTitle: "RU anchor", wantPostLocs: []string{"ru"},
		},
		{
			name: "legacy anchor does not borrow a later duplicate's translation", email: "legacy-dup@test.com", slug: "legacy-dup",
			setup: func(t *testing.T, tdb *testhelper.TestDB, userID int) int {
				legacyID := insertLegacyDuplicate(t, tdb, userID, "legacy-dup", "ru")
				duplicateID := insertLegacyDuplicate(t, tdb, userID, "legacy-dup", "ru")
				insertLocale(t, tdb, duplicateID, "en", "EN duplicate", "en summary", "needle-en-legacy-dup", articleDoc("en"))
				setLocalePublished(t, tdb, duplicateID, "en", true)
				return legacyID
			},
			wantRoute: model.RouteModeLegacy, wantResolved: []string{"ru"},
			wantLocales: map[string]string{"en": ""},
		},
		{
			name: "lowest visible anchor owns the translation", email: "owner@test.com", slug: "owned-translation",
			setup: func(t *testing.T, tdb *testhelper.TestDB, userID int) int {
				legacyID := insertLegacyDuplicate(t, tdb, userID, "owned-translation", "ru")
				insertLocale(t, tdb, legacyID, "en", "Anchor translation", "anchor summary", "needle-anchor", articleDoc("en"))
				setLocalePublished(t, tdb, legacyID, "en", true)
				duplicateID := insertLegacyDuplicate(t, tdb, userID, "owned-translation", "ru")
				insertLocale(t, tdb, duplicateID, "en", "EN duplicate", "en summary", "needle-en-duplicate", articleDoc("en"))
				setLocalePublished(t, tdb, duplicateID, "en", true)
				return legacyID
			},
			wantLocales: map[string]string{"en": "Anchor translation"},
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			tdb := newDuplicatePermittingDB(t)
			ctx := context.Background()
			ownerID := tc.setup(t, tdb, insertBaselineUser(t, tdb, tc.email))
			articles := repository.NewArticleRepository(tdb.DB)

			if tc.wantRoute != "" {
				resolution, err := articles.ResolveBySlug(ctx, tc.slug)
				require.NoError(t, err)
				assert.Equal(t, tc.wantRoute, resolution.RouteMode)
				require.Len(t, resolution.Locales, len(tc.wantResolved))
				for i, locale := range tc.wantResolved {
					assert.Equal(t, locale, resolution.Locales[i].Locale)
				}
			}

			for locale, wantTitle := range tc.wantLocales {
				got, err := articles.FindPublishedLocale(ctx, tc.slug, locale)
				if wantTitle == "" {
					assert.ErrorIs(t, err, sql.ErrNoRows, "the %s locale belongs to a duplicate, not to the resolved anchor", locale)
					continue
				}
				require.NoError(t, err)
				assert.Equal(t, wantTitle, got.Title)
			}

			post, err := repository.NewPostRepository(tdb.DB).FindPublishedBySlug(ctx, tc.slug)
			require.NoError(t, err)
			assert.Equal(t, ownerID, post.ID, "the returned post is the resolved anchor")
			if tc.wantPostTitle != "" {
				assert.Equal(t, tc.wantPostTitle, post.Title)
			}
			if tc.wantPostLocs != nil {
				require.Len(t, post.Locales, len(tc.wantPostLocs))
				for i, locale := range tc.wantPostLocs {
					assert.Equal(t, locale, post.Locales[i].Locale)
				}
			}
		})
	}
}
