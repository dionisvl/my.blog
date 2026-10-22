package handler

import "net/http"

type ContactsHandler struct {
	website string
}

func NewContactsHandler(mainHost string) *ContactsHandler {
	return &ContactsHandler{website: "https://" + mainHost}
}

func (h *ContactsHandler) ServeHTTP(w http.ResponseWriter, _ *http.Request) {
	RespondJSON(w, http.StatusOK, map[string]string{
		"email":   "info@phpqa.ru",
		"website": h.website,
	})
}
