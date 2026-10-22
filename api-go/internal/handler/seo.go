package handler

import (
	"log/slog"
	"net/http"
	"os"
	"path/filepath"
	"strings"
)

type SEOHandler struct {
	seoDir string
	host   string
	logger *slog.Logger
}

// NewSEOHandler serves per-host SEO files for the canonical host only;
// Traefik redirects every other host to it.
func NewSEOHandler(seoDir, host string, logger *slog.Logger) *SEOHandler {
	return &SEOHandler{seoDir: seoDir, host: host, logger: logger}
}

func (h *SEOHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	base := strings.TrimPrefix(r.URL.Path, "/")
	ext := filepath.Ext(base)
	fileType := strings.TrimSuffix(base, ext)
	extNoDot := strings.TrimPrefix(ext, ".")

	filename := fileType + "-" + h.host + "." + extNoDot
	fullPath := filepath.Join(h.seoDir, filename)

	data, err := os.ReadFile(fullPath)
	if err != nil {
		if os.IsNotExist(err) {
			http.NotFound(w, r)
			return
		}
		h.logger.Error("seo file read failed", slog.String("path", fullPath), slog.String("error", err.Error()))
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	contentType := "text/plain; charset=utf-8"
	if extNoDot == "xml" {
		contentType = "application/xml; charset=utf-8"
	}
	w.Header().Set("Content-Type", contentType)
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write(data)
}
