package middleware

import (
	"crypto/subtle"
	"encoding/json"
	"net/http"
	"strings"
)

func APIKeyAuth(apiKey string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			key := r.Header.Get("X-API-Key")
			if strings.TrimSpace(apiKey) == "" || subtle.ConstantTimeCompare([]byte(key), []byte(apiKey)) != 1 {
				w.Header().Set("Content-Type", "application/json")
				w.WriteHeader(http.StatusUnauthorized)
				_ = json.NewEncoder(w).Encode(map[string]string{"error": "invalid API key"})
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}
