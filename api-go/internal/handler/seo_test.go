package handler

import (
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestSEOHandlerServesFilesForConfiguredHost(t *testing.T) {
	dir := t.TempDir()
	require.NoError(t, os.WriteFile(filepath.Join(dir, "sitemap-example.com.xml"), []byte("<urlset/>"), 0o600))
	require.NoError(t, os.WriteFile(filepath.Join(dir, "robots-example.com.txt"), []byte("User-agent: *"), 0o600))

	h := NewSEOHandler(dir, "example.com", slog.New(slog.NewTextHandler(io.Discard, nil)))

	tests := []struct {
		name        string
		path        string
		requestHost string
		wantStatus  int
		wantType    string
		wantBody    string
	}{
		{"sitemap", "/sitemap.xml", "example.com", http.StatusOK, "application/xml; charset=utf-8", "<urlset/>"},
		{"robots", "/robots.txt", "example.com", http.StatusOK, "text/plain; charset=utf-8", "User-agent: *"},
		{"request host is ignored", "/sitemap.xml", "evil.com", http.StatusOK, "application/xml; charset=utf-8", "<urlset/>"},
		{"missing file", "/llms.txt", "example.com", http.StatusNotFound, "", ""},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			req := httptest.NewRequest(http.MethodGet, tt.path, nil)
			req.Host = tt.requestHost
			rec := httptest.NewRecorder()

			h.ServeHTTP(rec, req)

			assert.Equal(t, tt.wantStatus, rec.Code)
			if tt.wantStatus == http.StatusOK {
				assert.Equal(t, tt.wantType, rec.Header().Get("Content-Type"))
				assert.Equal(t, tt.wantBody, rec.Body.String())
			}
		})
	}
}
