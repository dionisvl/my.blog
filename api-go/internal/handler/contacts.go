package handler

import "net/http"

type ContactsHandler struct {
	email   string
	website string
}

func NewContactsHandler(mainHost, email string) *ContactsHandler {
	return &ContactsHandler{email: email, website: "https://" + mainHost}
}

func (h *ContactsHandler) ServeHTTP(w http.ResponseWriter, _ *http.Request) {
	RespondJSON(w, http.StatusOK, map[string]string{
		"email":   h.email,
		"website": h.website,
	})
}
