package handler

import (
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestStorageHandlerImmutableCacheOnlyForHashedWebp(t *testing.T) {
	dir := t.TempDir()
	require.NoError(t, os.Mkdir(filepath.Join(dir, "uploads"), 0o755))
	hashed := "2026-11-13_10-00-00_0123456789abcdef.webp"
	for _, name := range []string{hashed, "old.png", "manual.webp"} {
		require.NoError(t, os.WriteFile(filepath.Join(dir, "uploads", name), []byte("x"), 0o644))
	}
	h := NewStorageHandler(dir)

	tests := []struct {
		name      string
		path      string
		wantCache string
	}{
		{"hashed webp is immutable", "/storage/uploads/" + hashed, "public, max-age=31536000, immutable"},
		{"png is not immutable", "/storage/uploads/old.png", "public, max-age=86400"},
		{"unhashed webp is not immutable", "/storage/uploads/manual.webp", "public, max-age=86400"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			w := httptest.NewRecorder()
			h.ServeHTTP(w, httptest.NewRequest(http.MethodGet, tt.path, nil))

			assert.Equal(t, http.StatusOK, w.Code)
			assert.Equal(t, tt.wantCache, w.Header().Get("Cache-Control"))
		})
	}
}
