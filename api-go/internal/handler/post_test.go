package handler

import (
	"context"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/go-chi/chi/v5"
	"github.com/stretchr/testify/assert"

	"api-go/internal/model"
)

// fakePostRepo implements repository.PostRepository for testing
type fakePostRepo struct {
	post           *model.Post
	incrementCalls int
}

func (f *fakePostRepo) FindPublishedBySlug(ctx context.Context, slug string) (*model.Post, error) {
	return f.post, nil
}

func (f *fakePostRepo) IncrementViews(ctx context.Context, postID int) (int, error) {
	f.incrementCalls++
	return f.post.ViewsCount + 1, nil
}

func (f *fakePostRepo) FindFeatured(ctx context.Context, limit int) ([]model.Post, error) {
	return []model.Post{}, nil
}

func (f *fakePostRepo) FindRecentPublished(ctx context.Context, limit int) ([]model.Post, error) {
	return []model.Post{}, nil
}

func (f *fakePostRepo) FindPublishedPaginated(ctx context.Context, page, perPage int) ([]model.Post, int, error) {
	return []model.Post{}, 0, nil
}

func (f *fakePostRepo) FindPublishedByCategorySlugPaginated(ctx context.Context, slug string, page, perPage int) ([]model.Post, int, error) {
	return []model.Post{}, 0, nil
}

func (f *fakePostRepo) FindPublishedByTagSlugPaginated(ctx context.Context, slug string, page, perPage int) ([]model.Post, int, error) {
	return []model.Post{}, 0, nil
}

func (f *fakePostRepo) SearchPublished(ctx context.Context, query string, limit int) ([]model.Post, error) {
	return []model.Post{}, nil
}

// fakeCategoryRepo implements repository.CategoryRepository for testing
type fakeCategoryRepo struct{}

func (f *fakeCategoryRepo) FindWithPostCounts(ctx context.Context) ([]model.CategoryWithCount, error) {
	return []model.CategoryWithCount{}, nil
}

func (f *fakeCategoryRepo) FindBySlug(ctx context.Context, slug string) (*model.Category, error) {
	return nil, nil
}

// fakeAphorismRepo implements repository.AphorismRepository for testing
type fakeAphorismRepo struct{}

func (f *fakeAphorismRepo) FindRandom(ctx context.Context) (*model.Aphorism, error) {
	return nil, nil
}

func TestPostHandler_ViewCounting(t *testing.T) {
	tests := []struct {
		name          string
		cookie        *http.Cookie
		wantIncrement int
		wantSetCookie bool
	}{
		{name: "no cookie counts the view and sets the cookie", wantIncrement: 1, wantSetCookie: true},
		{
			name:          "cookie from today suppresses the view",
			cookie:        &http.Cookie{Name: "viewedPostToday42", Value: "2006-01-02 15:04:05"},
			wantIncrement: 0,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			postRepo := &fakePostRepo{post: &model.Post{ID: 42, Title: "Test Post", Slug: "test-post"}}
			logger := slog.New(slog.NewTextHandler(io.Discard, nil))
			handler := NewPostHandler(postRepo, &fakeCategoryRepo{}, &fakeAphorismRepo{}, logger)

			router := chi.NewRouter()
			router.Get("/api/post/{slug}", handler.ServeHTTP)

			req := httptest.NewRequest(http.MethodGet, "/api/post/test-post", nil)
			if tt.cookie != nil {
				req.AddCookie(tt.cookie)
			}
			w := httptest.NewRecorder()
			router.ServeHTTP(w, req)

			assert.Equal(t, http.StatusOK, w.Code)
			assert.Equal(t, tt.wantIncrement, postRepo.incrementCalls)

			var viewCookie *http.Cookie
			for _, c := range w.Result().Cookies() {
				if strings.HasPrefix(c.Name, "viewedPostToday") {
					viewCookie = c
				}
			}
			if !tt.wantSetCookie {
				assert.Nil(t, viewCookie)
				return
			}
			if assert.NotNil(t, viewCookie) {
				assert.Equal(t, "viewedPostToday42", viewCookie.Name)
				assert.True(t, viewCookie.HttpOnly)
				assert.NotZero(t, viewCookie.Expires)
			}
		})
	}
}
