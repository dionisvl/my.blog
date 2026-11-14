package handler

import (
	"database/sql"
	"errors"
	"log/slog"
	"net/http"

	"github.com/go-chi/chi/v5"

	"api-go/internal/model"
	"api-go/internal/repository"
)

type CategoryHandler struct {
	posts      repository.PostRepository
	categories repository.CategoryRepository
	logger     *slog.Logger
}

func NewCategoryHandler(posts repository.PostRepository, categories repository.CategoryRepository, logger *slog.Logger) *CategoryHandler {
	return &CategoryHandler{posts: posts, categories: categories, logger: logger}
}

type categoryResponse struct {
	Posts      []model.Post    `json:"posts"`
	Category   *model.Category `json:"category"`
	Pagination Pagination      `json:"pagination"`
}

func (h *CategoryHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	slug := chi.URLParam(r, "slug")

	category, err := h.categories.FindBySlug(r.Context(), slug)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			RespondError(w, http.StatusNotFound, "category not found")
		} else {
			h.logger.Error("category fetch failed", slog.String("error", err.Error()))
			RespondError(w, http.StatusInternalServerError, "internal server error")
		}
		return
	}

	page := PageFromQuery(r, 1)
	const perPage = 10

	posts, total, err := h.posts.FindPublishedByCategorySlugPaginated(requestContext(r), slug, page, perPage)
	if err != nil {
		h.logger.Error("category posts fetch failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}

	RespondJSON(w, http.StatusOK, categoryResponse{
		Posts:      EmptyIfNil(posts),
		Category:   category,
		Pagination: BuildPagination(page, perPage, total),
	})
}
