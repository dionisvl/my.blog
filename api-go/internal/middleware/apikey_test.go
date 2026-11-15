package middleware_test

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/stretchr/testify/assert"

	"api-go/internal/middleware"
)

func TestAPIKeyAuth(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name       string
		configured string
		header     string
		wantStatus int
		wantCalled bool
	}{
		{"matching key", "secret", "secret", http.StatusOK, true},
		{"empty configured key rejects everything", "", "secret", http.StatusUnauthorized, false},
		{"empty configured key rejects empty header", "", "", http.StatusUnauthorized, false},
		{"missing header", "secret", "", http.StatusUnauthorized, false},
		{"wrong key", "secret", "other", http.StatusUnauthorized, false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			called := false
			handler := middleware.APIKeyAuth(tt.configured)(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
				called = true
				w.WriteHeader(http.StatusOK)
			}))

			req := httptest.NewRequest(http.MethodGet, "/api", nil)
			if tt.header != "" {
				req.Header.Set("X-API-Key", tt.header)
			}
			rec := httptest.NewRecorder()
			handler.ServeHTTP(rec, req)

			assert.Equal(t, tt.wantStatus, rec.Code)
			assert.Equal(t, tt.wantCalled, called)
			if tt.wantStatus == http.StatusUnauthorized {
				assert.JSONEq(t, `{"error":"invalid API key"}`, rec.Body.String())
			}
		})
	}
}
