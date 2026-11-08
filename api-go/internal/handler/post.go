package handler

import (
	"database/sql"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"time"

	"github.com/go-chi/chi/v5"
	"golang.org/x/sync/errgroup"

	"api-go/internal/model"
	"api-go/internal/repository"
)

type PostHandler struct {
	posts      repository.PostRepository
	categories repository.CategoryRepository
	aphorisms  repository.AphorismRepository
	logger     *slog.Logger
}

func NewPostHandler(
	posts repository.PostRepository,
	categories repository.CategoryRepository,
	aphorisms repository.AphorismRepository,
	logger *slog.Logger,
) *PostHandler {
	return &PostHandler{posts: posts, categories: categories, aphorisms: aphorisms, logger: logger}
}

type postResponse struct {
	Post          *model.Post               `json:"post"`
	FeaturedPosts []model.Post              `json:"featured_posts"`
	RecentPosts   []model.Post              `json:"recent_posts"`
	Categories    []model.CategoryWithCount `json:"categories"`
	Aphorism      *model.Aphorism           `json:"aphorism,omitempty"`
}

func (h *PostHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	slug := chi.URLParam(r, "slug")

	post, err := h.posts.FindPublishedBySlug(r.Context(), slug)
	if err != nil {
		if isNotFound(err) {
			RespondError(w, http.StatusNotFound, "post not found")
		} else {
			h.logger.Error("post fetch failed", slog.String("error", err.Error()))
			RespondError(w, http.StatusInternalServerError, "internal server error")
		}
		return
	}

	g, ctx := errgroup.WithContext(requestContext(r))

	var categories []model.CategoryWithCount
	var featured []model.Post
	var recent []model.Post
	var aphorism *model.Aphorism

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
	g.Go(func() error {
		var err error
		aphorism, err = h.aphorisms.FindRandom(ctx)
		return err
	})

	if err := g.Wait(); err != nil {
		h.logger.Error("post show fetch failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}

	cookieName := fmt.Sprintf("viewedPostToday%d", post.ID)
	if _, err := r.Cookie(cookieName); err != nil {
		viewsCount, incrementErr := h.posts.IncrementViews(r.Context(), post.ID)
		if incrementErr != nil {
			h.logger.Error("increment views failed", slog.String("error", incrementErr.Error()))
		} else {
			post.ViewsCount = viewsCount
			w.Header().Set("X-View-Counted", "true")
			http.SetCookie(w, &http.Cookie{
				Name:     cookieName,
				Value:    time.Now().Format("2006-01-02 15:04:05"),
				Expires:  time.Now().Add(24 * time.Hour),
				Path:     "/",
				HttpOnly: true,
			})
		}
	}

	RespondJSON(w, http.StatusOK, postResponse{
		Post:          post,
		FeaturedPosts: featured,
		RecentPosts:   recent,
		Categories:    categories,
		Aphorism:      aphorism,
	})
}

func isNotFound(err error) bool {
	return errors.Is(err, sql.ErrNoRows)
}
