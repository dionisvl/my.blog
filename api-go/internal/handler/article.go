package handler

import (
	"log/slog"
	"net/http"

	"github.com/go-chi/chi/v5"
	"golang.org/x/sync/errgroup"

	"api-go/internal/model"
	"api-go/internal/repository"
)

// ArticleHandler serves the read-only API of modular articles and legacy translations.
//
// Unlike PostHandler it NEVER increments views and NEVER sets cookies, so Astro can call it for
// neutral-URL redirects, crawlers and previews without side effects.
type ArticleHandler struct {
	posts      repository.PostRepository
	articles   repository.ArticleRepository
	categories repository.CategoryRepository
	aphorisms  repository.AphorismRepository
	logger     *slog.Logger
}

func NewArticleHandler(
	posts repository.PostRepository,
	articles repository.ArticleRepository,
	categories repository.CategoryRepository,
	aphorisms repository.AphorismRepository,
	logger *slog.Logger,
) *ArticleHandler {
	return &ArticleHandler{posts: posts, articles: articles, categories: categories, aphorisms: aphorisms, logger: logger}
}

type articleResponse struct {
	ContractVersion int                       `json:"contract_version"`
	Post            *model.Post               `json:"post"`
	Article         *model.PublishedLocale    `json:"article"`
	Locales         []model.LocaleLink        `json:"locales"`
	FeaturedPosts   []model.Post              `json:"featured_posts"`
	RecentPosts     []model.Post              `json:"recent_posts"`
	Categories      []model.CategoryWithCount `json:"categories"`
	Aphorism        *model.Aphorism           `json:"aphorism,omitempty"`
}

// Resolve handles GET /api/post/{slug}/resolve.
func (h *ArticleHandler) Resolve(w http.ResponseWriter, r *http.Request) {
	slug := chi.URLParam(r, "slug")

	resolution, err := h.articles.ResolveBySlug(r.Context(), slug)
	if err != nil {
		if isNotFound(err) {
			RespondError(w, http.StatusNotFound, "post not found")
		} else {
			h.logger.Error("article resolve failed", slog.String("error", err.Error()))
			RespondError(w, http.StatusInternalServerError, "internal server error")
		}
		return
	}

	// The answer drives redirects that depend on what is published right now.
	w.Header().Set("Cache-Control", "private, no-store")
	RespondJSON(w, http.StatusOK, resolution)
}

// Locale handles GET /api/post/{slug}/locale/{locale}: the published content of one locale.
func (h *ArticleHandler) Locale(w http.ResponseWriter, r *http.Request) {
	slug := chi.URLParam(r, "slug")
	locale := chi.URLParam(r, "locale")

	if !model.IsSupportedLocale(locale) {
		RespondError(w, http.StatusNotFound, "article locale not found")
		return
	}

	published, err := h.articles.FindPublishedLocale(r.Context(), slug, locale)
	if err != nil {
		if isNotFound(err) {
			RespondError(w, http.StatusNotFound, "article locale not found")
		} else {
			h.logger.Error("article locale fetch failed", slog.String("error", err.Error()))
			RespondError(w, http.StatusInternalServerError, "internal server error")
		}
		return
	}

	post, err := h.posts.FindPublishedBySlug(r.Context(), slug)
	if err != nil {
		if isNotFound(err) {
			RespondError(w, http.StatusNotFound, "article locale not found")
		} else {
			h.logger.Error("article anchor fetch failed", slog.String("error", err.Error()))
			RespondError(w, http.StatusInternalServerError, "internal server error")
		}
		return
	}
	if !post.IsModular() && (post.OriginalLocale == nil || locale == *post.OriginalLocale) {
		RespondError(w, http.StatusNotFound, "article locale not found")
		return
	}

	g, ctx := errgroup.WithContext(r.Context())

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
		h.logger.Error("article sidebar fetch failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}

	RespondJSON(w, http.StatusOK, articleResponse{
		ContractVersion: model.ContractVersion,
		Post:            post,
		Article:         published,
		Locales:         EmptyIfNil(post.Locales),
		FeaturedPosts:   EmptyIfNil(featured),
		RecentPosts:     EmptyIfNil(recent),
		Categories:      EmptyIfNil(categories),
		Aphorism:        aphorism,
	})
}
