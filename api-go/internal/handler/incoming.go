package handler

import (
	"encoding/json"
	"log/slog"
	"net/http"
	"strings"

	"api-go/internal/repository"
)

const maxIncomingBodyBytes = 16 << 10 // 16 KB

type IncomingHandler struct {
	incomings repository.IncomingRepository
	logger    *slog.Logger
}

func NewIncomingHandler(incomings repository.IncomingRepository, logger *slog.Logger) *IncomingHandler {
	return &IncomingHandler{incomings: incomings, logger: logger}
}

type incomingRequest struct {
	Name     string `json:"name"`
	Email    string `json:"email"`
	Phone    string `json:"phone"`
	Message  string `json:"message"`
	CountMe  int    `json:"countMe"`
	Honeypot string `json:"honeypot"`
}

func (h *IncomingHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	r.Body = http.MaxBytesReader(w, r.Body, maxIncomingBodyBytes)

	var req incomingRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		RespondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	req.Name = strings.TrimSpace(req.Name)
	req.Message = strings.TrimSpace(req.Message)

	if req.Name == "" || len(req.Name) > 255 {
		RespondError(w, http.StatusUnprocessableEntity, "name is required")
		return
	}
	if req.Message == "" || len(req.Message) > 5000 {
		RespondError(w, http.StatusUnprocessableEntity, "message is required")
		return
	}
	if req.CountMe < 3 {
		RespondError(w, http.StatusUnprocessableEntity, "anti-bot check failed")
		return
	}
	if req.Honeypot != "" {
		RespondError(w, http.StatusUnprocessableEntity, "anti-bot check failed")
		return
	}

	incoming, err := h.incomings.Create(r.Context(), req.Name, req.Email, req.Phone, req.Message)
	if err != nil {
		h.logger.Error("incoming create failed", slog.String("error", err.Error()))
		RespondError(w, http.StatusInternalServerError, "internal server error")
		return
	}

	RespondJSON(w, http.StatusCreated, map[string]any{
		"status":  "ok",
		"message": "your message has been received",
		"id":      incoming.ID,
	})
}
