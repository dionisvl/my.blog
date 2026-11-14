package handler

import (
	"encoding/xml"
	"errors"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"api-go/internal/model"
)

func TestSEOHandlerServesFilesForConfiguredHost(t *testing.T) {
	dir := t.TempDir()
	require.NoError(t, os.WriteFile(filepath.Join(dir, "sitemap-example.com.xml"), []byte("<urlset/>"), 0o600))
	require.NoError(t, os.WriteFile(filepath.Join(dir, "robots-example.com.txt"), []byte("User-agent: *"), 0o600))

	h := NewSEOHandler(dir, "example.com", slog.New(slog.NewTextHandler(io.Discard, nil)), &fakeArticleRepo{})

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

func TestSEOHandlerUsesAllowlistedRequestHostForRobotsAndSitemap(t *testing.T) {
	dir := t.TempDir()
	robots := []byte("User-agent: *\nSitemap: https://example.com/sitemap.xml\n")
	sitemap := []byte(`<urlset><url><loc>https://example.com/</loc></url></urlset>`)
	require.NoError(t, os.WriteFile(filepath.Join(dir, "robots-example.com.txt"), robots, 0o600))
	require.NoError(t, os.WriteFile(filepath.Join(dir, "sitemap-example.com.xml"), sitemap, 0o600))
	h := NewSEOHandler(dir, "example.com", slog.New(slog.NewTextHandler(io.Discard, nil)), nil)

	for _, tc := range []struct{ host, path, want string }{
		{"www.example.com", "/robots.txt", "Sitemap: https://www.example.com/sitemap.xml"},
		{"www.example.com", "/sitemap.xml", "https://www.example.com/"},
		{"evil.example", "/robots.txt", "Sitemap: https://example.com/sitemap.xml"},
	} {
		req := httptest.NewRequest(http.MethodGet, tc.path, nil)
		req.Host = tc.host
		rec := httptest.NewRecorder()
		h.ServeHTTP(rec, req)
		require.Equal(t, http.StatusOK, rec.Code)
		assert.Contains(t, rec.Body.String(), tc.want)
	}
}

func TestSEOHandlerAddsPublishedArticleLocalesAndHreflang(t *testing.T) {
	dir := t.TempDir()
	static := []byte(`<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/old-post</loc></url></urlset>`)
	require.NoError(t, os.WriteFile(filepath.Join(dir, "sitemap-example.com.xml"), static, 0o600))

	repo := &fakeArticleRepo{sitemapLocales: []model.PublishedSitemapLocale{
		{Slug: "cards", Locale: "ru", PublishedAt: time.Date(2026, 11, 1, 10, 0, 0, 0, time.UTC)},
		{Slug: "cards", Locale: "en", PublishedAt: time.Date(2026, 11, 2, 10, 0, 0, 0, time.UTC)},
		{Slug: "ru-only", Locale: "ru", PublishedAt: time.Date(2026, 11, 3, 10, 0, 0, 0, time.UTC)},
		{Slug: "draft", Locale: "en", PublishedAt: time.Time{}},
	}}
	h := NewSEOHandler(dir, "example.com", slog.New(slog.NewTextHandler(io.Discard, nil)), repo)
	rec := httptest.NewRecorder()
	h.ServeHTTP(rec, httptest.NewRequest(http.MethodGet, "/sitemap.xml", nil))

	require.Equal(t, http.StatusOK, rec.Code)
	assert.Equal(t, "public, max-age=300", rec.Header().Get("Cache-Control"))
	assert.Contains(t, rec.Body.String(), "https://example.com/old-post")
	assert.Contains(t, rec.Body.String(), "https://example.com/ru/post/cards")
	assert.Contains(t, rec.Body.String(), "https://example.com/en/post/cards")
	assert.Contains(t, rec.Body.String(), "https://example.com/ru/post/ru-only")
	assert.Contains(t, rec.Body.String(), `hreflang="x-default" href="https://example.com/en/post/cards"`)
	assert.NotContains(t, rec.Body.String(), `hreflang="x-default" href="https://example.com/en/post/ru-only"`)
	assert.NotContains(t, rec.Body.String(), "/post/draft")
	assert.NotContains(t, rec.Body.String(), "https://www.example.com/")
	assert.NotContains(t, rec.Body.String(), "https://example.com/post/cards")
	assert.Contains(t, rec.Body.String(), `xmlns:xhtml="http://www.w3.org/1999/xhtml"`)

	decoder := xml.NewDecoder(strings.NewReader(rec.Body.String()))
	for {
		_, err := decoder.Token()
		if errors.Is(err, io.EOF) {
			break
		}
		require.NoError(t, err)
	}
}

func TestSEOHandlerFallsBackToOriginalSitemapWhenArticleQueryFails(t *testing.T) {
	dir := t.TempDir()
	static := []byte(`<urlset><url><loc>https://example.com/legacy</loc></url></urlset>`)
	require.NoError(t, os.WriteFile(filepath.Join(dir, "sitemap-example.com.xml"), static, 0o600))

	repo := &fakeArticleRepo{sitemapErr: errors.New("database unavailable")}
	h := NewSEOHandler(dir, "example.com", slog.New(slog.NewTextHandler(io.Discard, nil)), repo)
	rec := httptest.NewRecorder()
	h.ServeHTTP(rec, httptest.NewRequest(http.MethodGet, "/sitemap.xml", nil))

	require.Equal(t, http.StatusOK, rec.Code)
	assert.Equal(t, string(static), rec.Body.String())
}

func TestAddArticleLocalesDoesNotDuplicateExistingCanonicalURL(t *testing.T) {
	static := []byte(`<urlset><url><loc>https://example.com/ru/post/cards</loc></url></urlset>`)
	locales := []model.PublishedSitemapLocale{{
		Slug: "cards", Locale: "ru", PublishedAt: time.Date(2026, 11, 1, 10, 0, 0, 0, time.UTC),
	}}
	result, err := addArticleLocalesToSitemap(static, "example.com", locales)
	require.NoError(t, err)
	assert.Equal(t, 1, strings.Count(string(result), "https://example.com/ru/post/cards"))
}
