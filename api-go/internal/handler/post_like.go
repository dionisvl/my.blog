package handler

import (
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
	"strconv"
	"time"

	"github.com/go-chi/chi/v5"
	"github.com/go-chi/chi/v5/middleware"

	"api-go/internal/repository"
)

type PostLikeHandler struct {
	likes  repository.PostLikeRepository
	logger *slog.Logger
}

func NewPostLikeHandler(likes repository.PostLikeRepository, logger *slog.Logger) *PostLikeHandler {
	return &PostLikeHandler{likes: likes, logger: logger}
}

type postLikeRequest struct {
	DeviceMemory *int `json:"device_memory"`
}

type postLikeResponse struct {
	Status string `json:"status"`
	Data   string `json:"data"`
}

func (h *PostLikeHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	postIDStr := chi.URLParam(r, "postId")
	postID, err := strconv.Atoi(postIDStr)
	if err != nil || postID <= 0 {
		RespondJSON(w, http.StatusOK, postLikeResponse{Status: "error", Data: "empty post_id"})
		return
	}

	cookieName := fmt.Sprintf("likedPostToday%d", postID)

	cookie, err := r.Cookie(cookieName)
	if err == nil {
		// already liked — unlike by the stable like ID stored in the cookie
		likeID, parseErr := strconv.Atoi(cookie.Value)
		if parseErr == nil && likeID > 0 {
			if delErr := h.likes.DeleteByIDAndPost(r.Context(), likeID, postID); delErr != nil {
				h.logger.Error("delete like failed", slog.String("error", delErr.Error()))
			}
		}

		http.SetCookie(w, &http.Cookie{
			Name:    cookieName,
			Value:   "",
			Expires: time.Unix(0, 0),
			MaxAge:  -1,
			Path:    "/",
		})

		RespondJSON(w, http.StatusOK, postLikeResponse{Status: "ok", Data: "unliked"})
		return
	}

	// not liked yet — check post exists
	exists, err := h.likes.PostExists(r.Context(), postID)
	if err != nil {
		h.logger.Error("post exists check failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}
	if !exists {
		RespondJSON(w, http.StatusOK, postLikeResponse{Status: "error", Data: "post not found"})
		return
	}

	var req postLikeRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		req.DeviceMemory = nil
	}

	var ip *string
	if clientIP := middleware.GetClientIP(r.Context()); clientIP != "" {
		ip = &clientIP
	}
	like, err := h.likes.Create(r.Context(), postID, ip, req.DeviceMemory)
	if err != nil {
		h.logger.Error("create like failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}

	http.SetCookie(w, &http.Cookie{
		Name:     cookieName,
		Value:    strconv.Itoa(like.ID),
		Expires:  time.Now().Add(24 * time.Hour),
		Path:     "/",
		HttpOnly: true,
	})

	RespondJSON(w, http.StatusOK, postLikeResponse{Status: "ok", Data: "liked"})
}
