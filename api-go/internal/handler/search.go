package handler

import (
	"log/slog"
	"net/http"
	"strings"

	"api-go/internal/model"
	"api-go/internal/repository"
)

type SearchHandler struct {
	posts  repository.PostRepository
	logger *slog.Logger
}

func NewSearchHandler(posts repository.PostRepository, logger *slog.Logger) *SearchHandler {
	return &SearchHandler{posts: posts, logger: logger}
}

type searchResponse struct {
	Posts []model.Post `json:"posts"`
	Query string       `json:"query"`
}

func (h *SearchHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	query := strings.TrimSpace(r.URL.Query().Get("q"))

	var posts []model.Post
	if query != "" {
		var err error
		posts, err = h.posts.SearchPublished(requestContext(r), query, 20)
		if err != nil {
			h.logger.Error("search failed", slog.String("error", err.Error()))
			RespondError(w, http.StatusInternalServerError, "internal server error")
			return
		}
	}

	RespondJSON(w, http.StatusOK, searchResponse{
		Posts: EmptyIfNil(posts),
		Query: query,
	})
}