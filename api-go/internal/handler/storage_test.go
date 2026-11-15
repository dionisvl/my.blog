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

func TestStorageHandler(t *testing.T) {
	tempDir := t.TempDir()
	require.NoError(t, os.Mkdir(filepath.Join(tempDir, "subdir"), 0o755))
	require.NoError(t, os.WriteFile(filepath.Join(tempDir, "test.txt"), []byte("test content"), 0o644))
	require.NoError(t, os.WriteFile(filepath.Join(tempDir, "subdir", "subfile.txt"), []byte("sub content"), 0o644))

	handler := NewStorageHandler(tempDir)

	tests := []struct {
		name         string
		path         string
		wantStatus   int
		wantBody     string
		wantCache    string
		wantNoListed []string
	}{
		{name: "file", path: "/storage/test.txt", wantStatus: http.StatusOK, wantBody: "test content", wantCache: "public, max-age=86400"},
		{name: "nested file", path: "/storage/subdir/subfile.txt", wantStatus: http.StatusOK, wantBody: "sub content", wantCache: "public, max-age=86400"},
		{name: "root has no listing", path: "/storage/", wantStatus: http.StatusNotFound, wantNoListed: []string{"test.txt", "subdir"}},
		{name: "directory has no listing", path: "/storage/subdir/", wantStatus: http.StatusNotFound, wantNoListed: []string{"subfile.txt"}},
		{name: "missing file", path: "/storage/nonexistent.txt", wantStatus: http.StatusNotFound},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			w := httptest.NewRecorder()
			handler.ServeHTTP(w, httptest.NewRequest(http.MethodGet, tt.path, nil))

			assert.Equal(t, tt.wantStatus, w.Code)
			if tt.wantBody != "" {
				assert.Equal(t, tt.wantBody, w.Body.String())
			}
			if tt.wantCache != "" {
				assert.Equal(t, tt.wantCache, w.Header().Get("Cache-Control"))
			}
			for _, name := range tt.wantNoListed {
				assert.NotContains(t, w.Body.String(), name)
			}
		})
	}
}
