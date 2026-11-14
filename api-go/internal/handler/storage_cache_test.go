package handler

import (
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
	require.NoError(t, os.WriteFile(filepath.Join(dir, "uploads", hashed), []byte("x"), 0o644))
	require.NoError(t, os.WriteFile(filepath.Join(dir, "uploads", "old.png"), []byte("x"), 0o644))
	require.NoError(t, os.WriteFile(filepath.Join(dir, "uploads", "manual.webp"), []byte("x"), 0o644))
	h := NewStorageHandler(dir)

	for path, want := range map[string]string{
		"/storage/uploads/" + hashed:   "public, max-age=31536000, immutable",
		"/storage/uploads/old.png":     "public, max-age=86400",
		"/storage/uploads/manual.webp": "public, max-age=86400",
	} {
		w := httptest.NewRecorder()
		h.ServeHTTP(w, httptest.NewRequest("GET", path, nil))
		assert.Equal(t, 200, w.Code, path)
		assert.Equal(t, want, w.Header().Get("Cache-Control"), path)
	}
}
