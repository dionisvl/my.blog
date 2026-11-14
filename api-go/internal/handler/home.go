package handler

import (
	"context"
	"log/slog"
	"net/http"

	"golang.org/x/sync/errgroup"

	"api-go/internal/model"
	"api-go/internal/repository"
)

type HomeHandler struct {
	posts      repository.PostRepository
	categories repository.CategoryRepository
	logger     *slog.Logger
}

func NewHomeHandler(posts repository.PostRepository, categories repository.CategoryRepository, logger *slog.Logger) *HomeHandler {
	return &HomeHandler{posts: posts, categories: categories, logger: logger}
}

type homeResponse struct {
	Posts         []model.Post              `json:"posts"`
	FeaturedPosts []model.Post              `json:"featured_posts"`
	RecentPosts   []model.Post              `json:"recent_posts"`
	Categories    []model.CategoryWithCount `json:"categories"`
	Pagination    Pagination                `json:"pagination"`
}

func (h *HomeHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	page := PageFromQuery(r, 1)
	const perPage = 10

	g, ctx := errgroup.WithContext(requestContext(r))

	var posts []model.Post
	var total int
	var categories []model.CategoryWithCount
	var featured []model.Post
	var recent []model.Post

	g.Go(func() error {
		var err error
		posts, total, err = h.posts.FindPublishedPaginated(ctx, page, perPage)
		return err
	})
	g.Go(func() error {
		var err error
		categories, err = h.categories.FindWithPostCounts(ctx)
		return err
	})
	g.Go(func() error {
		var err error
		featured, err = h.posts.FindFeatured(ctx, 3)
		return err
	})
	g.Go(func() error {
		var err error
		recent, err = h.posts.FindRecentPublished(ctx, 4)
		return err
	})

	if err := g.Wait(); err != nil {
		h.logger.Error("home fetch failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}

	RespondJSON(w, http.StatusOK, homeResponse{
		Posts:         posts,
		FeaturedPosts: featured,
		RecentPosts:   recent,
		Categories:    categories,
		Pagination:    BuildPagination(page, perPage, total),
	})
}

// requestContext carries the reader's preferred language (?lang=) so article cards in lists follow it.
// Without a supported choice the cards are English.
func requestContext(r *http.Request) context.Context {
	lang := r.URL.Query().Get("lang")
	if !model.IsSupportedLocale(lang) {
		lang = model.LocaleEN
	}
	return repository.WithPreferredLocale(r.Context(), lang)
}
