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

// TestPostHandler_IncrementsViewsWithoutCookie verifies that a request without
// the viewedPostToday cookie increments views and sets the cookie.
func TestPostHandler_IncrementsViewsWithoutCookie(t *testing.T) {
	postRepo := &fakePostRepo{
		post: &model.Post{
			ID:    42,
			Title: "Test Post",
			Slug:  "test-post",
		},
	}
	catRepo := &fakeCategoryRepo{}
	aphorismRepo := &fakeAphorismRepo{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))

	handler := NewPostHandler(postRepo, catRepo, aphorismRepo, logger)

	// Create a chi router and mount the handler to populate the slug URL param.
	router := chi.NewRouter()
	router.Get("/api/post/{slug}", handler.ServeHTTP)

	// Create a request without the viewedPostToday cookie.
	req := httptest.NewRequest("GET", "/api/post/test-post", nil)
	w := httptest.NewRecorder()

	router.ServeHTTP(w, req)

	// Assert HTTP 200 response
	assert.Equal(t, http.StatusOK, w.Code, "expected HTTP 200")

	// Assert IncrementViews was called exactly once
	assert.Equal(t, 1, postRepo.incrementCalls, "expected IncrementViews to be called once")

	// Assert Set-Cookie header is present with the expected cookie name
	cookies := w.Result().Cookies()
	found := false
	for _, cookie := range cookies {
		if strings.HasPrefix(cookie.Name, "viewedPostToday") {
			found = true
			assert.Equal(t, "viewedPostToday42", cookie.Name, "expected cookie name viewedPostToday42")
			assert.True(t, cookie.HttpOnly, "expected HttpOnly flag to be set")
			assert.NotZero(t, cookie.Expires, "expected Expires to be set")
			break
		}
	}
	assert.True(t, found, "expected Set-Cookie header with viewedPostToday cookie")
}

// TestPostHandler_DoesNotIncrementWithCookie verifies that a request with
// the viewedPostToday cookie does not increment views.
func TestPostHandler_DoesNotIncrementWithCookie(t *testing.T) {
	postRepo := &fakePostRepo{
		post: &model.Post{
			ID:    42,
			Title: "Test Post",
			Slug:  "test-post",
		},
	}
	catRepo := &fakeCategoryRepo{}
	aphorismRepo := &fakeAphorismRepo{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))

	handler := NewPostHandler(postRepo, catRepo, aphorismRepo, logger)

	// Create a chi router and mount the handler to populate the slug URL param.
	router := chi.NewRouter()
	router.Get("/api/post/{slug}", handler.ServeHTTP)

	// Create a request with the viewedPostToday cookie already set.
	req := httptest.NewRequest("GET", "/api/post/test-post", nil)
	req.AddCookie(&http.Cookie{
		Name:  "viewedPostToday42",
		Value: "2006-01-02 15:04:05",
	})
	w := httptest.NewRecorder()

	router.ServeHTTP(w, req)

	// Assert HTTP 200 response
	assert.Equal(t, http.StatusOK, w.Code, "expected HTTP 200")

	// Assert IncrementViews was NOT called
	assert.Equal(t, 0, postRepo.incrementCalls, "expected IncrementViews to not be called")
}
