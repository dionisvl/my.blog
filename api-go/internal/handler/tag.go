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

type TagHandler struct {
	posts  repository.PostRepository
	tags   repository.TagRepository
	logger *slog.Logger
}

func NewTagHandler(posts repository.PostRepository, tags repository.TagRepository, logger *slog.Logger) *TagHandler {
	return &TagHandler{posts: posts, tags: tags, logger: logger}
}

type tagResponse struct {
	Posts      []model.Post `json:"posts"`
	Tag        *model.Tag   `json:"tag"`
	Pagination Pagination   `json:"pagination"`
}

func (h *TagHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	slug := chi.URLParam(r, "slug")

	tag, err := h.tags.FindBySlug(r.Context(), slug)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			RespondError(w, http.StatusNotFound, "tag not found")
		} else {
			h.logger.Error("tag fetch failed", slog.String("error", err.Error()))
			RespondError(w, http.StatusInternalServerError, "internal server error")
		}
		return
	}

	page := PageFromQuery(r, 1)
	const perPage = 10

	posts, total, err := h.posts.FindPublishedByTagSlugPaginated(requestContext(r), slug, page, perPage)
	if err != nil {
		h.logger.Error("tag posts fetch failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}

	RespondJSON(w, http.StatusOK, tagResponse{
		Posts:      EmptyIfNil(posts),
		Tag:        tag,
		Pagination: BuildPagination(page, perPage, total),
	})
}