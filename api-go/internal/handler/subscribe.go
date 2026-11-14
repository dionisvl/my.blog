package handler

import (
	"encoding/json"
	"log/slog"
	"net/http"
	"regexp"
	"strings"

	"github.com/google/uuid"

	"api-go/internal/repository"
)

const maxSubscribeBodyBytes = 1 << 10 // 1 KB
const maxEmailLength = 254            // RFC 5321

var emailRe = regexp.MustCompile(`^[^@\s]+@[^@\s]+\.[^@\s]+$`)

type SubscribeHandler struct {
	subscriptions repository.SubscriptionRepository
	logger        *slog.Logger
}

func NewSubscribeHandler(subscriptions repository.SubscriptionRepository, logger *slog.Logger) *SubscribeHandler {
	return &SubscribeHandler{subscriptions: subscriptions, logger: logger}
}

type subscribeRequest struct {
	Email string `json:"email"`
}

func (h *SubscribeHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	r.Body = http.MaxBytesReader(w, r.Body, maxSubscribeBodyBytes)

	var req subscribeRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		RespondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	req.Email = strings.ToLower(strings.TrimSpace(req.Email))
	if req.Email == "" || !emailRe.MatchString(req.Email) || len(req.Email) > maxEmailLength {
		RespondError(w, http.StatusUnprocessableEntity, "valid email is required")
		return
	}

	exists, err := h.subscriptions.EmailExists(r.Context(), req.Email)
	if err != nil {
		h.logger.Error("email exists check failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}
	if exists {
		RespondJSON(w, http.StatusOK, map[string]string{"status": "ok", "message": "subscribed"})
		return
	}

	token := uuid.New().String()
	_, err = h.subscriptions.Create(r.Context(), req.Email, token)
	if err != nil {
		h.logger.Error("subscription create failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}

	RespondJSON(w, http.StatusCreated, map[string]string{"status": "ok", "message": "subscribed"})
}
