package handler

import (
	"bytes"
	"encoding/xml"
	"errors"
	"fmt"
	"html"
	"io"
	"log/slog"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"api-go/internal/model"
	"api-go/internal/repository"
)

type SEOHandler struct {
	seoDir   string
	host     string
	logger   *slog.Logger
	articles repository.ArticleRepository
}

// NewSEOHandler serves per-host SEO files for the canonical host only;
// Traefik redirects every other host to it.
func NewSEOHandler(seoDir, host string, logger *slog.Logger, articles repository.ArticleRepository) *SEOHandler {
	return &SEOHandler{seoDir: seoDir, host: host, logger: logger, articles: articles}
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

	if fileType == "sitemap" && extNoDot == "xml" && h.articles != nil {
		locales, queryErr := h.articles.FindPublishedSitemapLocales(r.Context())
		if queryErr != nil {
			h.logger.Error("dynamic sitemap query failed; serving static sitemap", slog.String("error", queryErr.Error()))
		} else {
			dynamic, mergeErr := addArticleLocalesToSitemap(data, h.host, locales)
			if mergeErr != nil {
				h.logger.Error("dynamic sitemap merge failed; serving static sitemap", slog.String("error", mergeErr.Error()))
			} else {
				data = dynamic
			}
		}
	}

	contentType := "text/plain; charset=utf-8"
	if extNoDot == "xml" {
		contentType = "application/xml; charset=utf-8"
	}
	w.Header().Set("Content-Type", contentType)
	if fileType == "sitemap" && extNoDot == "xml" {
		w.Header().Set("Cache-Control", "public, max-age=300")
	}
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write(data)
}

func addArticleLocalesToSitemap(source []byte, host string, locales []model.PublishedSitemapLocale) ([]byte, error) {
	if len(locales) == 0 {
		return source, nil
	}
	if !validSitemapHost(host) {
		return nil, fmt.Errorf("configured sitemap host is not a canonical hostname")
	}

	locs, err := sitemapLocs(source)
	if err != nil {
		return nil, err
	}

	type article struct {
		byLocale map[string]time.Time
	}
	articles := make(map[string]*article)
	for _, item := range locales {
		if item.Slug == "" || strings.ContainsAny(item.Slug, "/\\?#") || !model.IsSupportedLocale(item.Locale) || item.PublishedAt.IsZero() {
			continue
		}
		entry := articles[item.Slug]
		if entry == nil {
			entry = &article{byLocale: make(map[string]time.Time)}
			articles[item.Slug] = entry
		}
		entry.byLocale[item.Locale] = item.PublishedAt
	}
	if len(articles) == 0 {
		return source, nil
	}

	start := bytes.Index(source, []byte("<urlset"))
	if start < 0 {
		return nil, fmt.Errorf("static sitemap has no urlset root")
	}
	openEndRelative := bytes.IndexByte(source[start:], '>')
	if openEndRelative < 0 {
		return nil, fmt.Errorf("static sitemap has an unterminated urlset root")
	}
	openEnd := start + openEndRelative
	closeStart := bytes.LastIndex(source, []byte("</urlset>"))
	if closeStart < openEnd {
		return nil, fmt.Errorf("static sitemap has no closing urlset tag")
	}

	rootOpen := string(source[start : openEnd+1])
	if !strings.Contains(rootOpen, "xmlns:xhtml=") {
		rootOpen = strings.TrimSuffix(rootOpen, ">") + ` xmlns:xhtml="http://www.w3.org/1999/xhtml">`
	}

	slugs := make([]string, 0, len(articles))
	for slug := range articles {
		slugs = append(slugs, slug)
	}
	sort.Strings(slugs)

	var additions bytes.Buffer
	for _, slug := range slugs {
		entry := articles[slug]
		publishedLocales := make([]string, 0, len(entry.byLocale))
		for _, locale := range []string{model.LocaleRU, model.LocaleEN} {
			if _, ok := entry.byLocale[locale]; ok {
				publishedLocales = append(publishedLocales, locale)
			}
		}
		for _, locale := range publishedLocales {
			path := "/" + locale + "/post/" + url.PathEscape(slug)
			loc := "https://" + host + path
			if locs[loc] {
				continue
			}

			fmt.Fprintf(&additions, "  <url><loc>%s</loc><lastmod>%s</lastmod>", escapeXML(loc), entry.byLocale[locale].UTC().Format(time.RFC3339))
			for _, alternateLocale := range publishedLocales {
				alternatePath := "/" + alternateLocale + "/post/" + url.PathEscape(slug)
				alternateURL := "https://" + host + alternatePath
				fmt.Fprintf(&additions, `<xhtml:link rel="alternate" hreflang="%s" href="%s"/>`, alternateLocale, html.EscapeString(alternateURL))
			}
			if len(publishedLocales) == 2 {
				xDefault := "https://" + host + "/en/post/" + url.PathEscape(slug)
				fmt.Fprintf(&additions, `<xhtml:link rel="alternate" hreflang="x-default" href="%s"/>`, html.EscapeString(xDefault))
			}
			additions.WriteString("</url>\n")
			locs[loc] = true
		}
	}
	if additions.Len() == 0 {
		return source, nil
	}

	result := make([]byte, 0, len(source)+additions.Len()+64)
	result = append(result, source[:start]...)
	result = append(result, rootOpen...)
	result = append(result, source[openEnd+1:closeStart]...)
	result = append(result, additions.Bytes()...)
	result = append(result, source[closeStart:]...)
	return result, nil
}

func validSitemapHost(host string) bool {
	if host == "" || strings.HasPrefix(strings.ToLower(host), "www.") || strings.ContainsAny(host, " \t\r\n") {
		return false
	}
	parsed, err := url.Parse("https://" + host)
	return err == nil && parsed.Host == host && parsed.Path == "" && parsed.RawQuery == "" && parsed.Fragment == ""
}

func sitemapLocs(source []byte) (map[string]bool, error) {
	decoder := xml.NewDecoder(bytes.NewReader(source))
	stack := make([]string, 0, 8)
	locs := make(map[string]bool)
	for {
		token, err := decoder.Token()
		if err != nil {
			if errors.Is(err, io.EOF) {
				return locs, nil
			}
			return nil, fmt.Errorf("parse static sitemap: %w", err)
		}
		switch element := token.(type) {
		case xml.StartElement:
			if element.Name.Local == "loc" && len(stack) > 0 && stack[len(stack)-1] == "url" {
				var loc string
				if err := decoder.DecodeElement(&loc, &element); err != nil {
					return nil, fmt.Errorf("read static sitemap URL: %w", err)
				}
				locs[strings.TrimSpace(loc)] = true
				continue
			}
			stack = append(stack, element.Name.Local)
		case xml.EndElement:
			if len(stack) > 0 {
				stack = stack[:len(stack)-1]
			}
		}
	}
}

func escapeXML(value string) string {
	var escaped bytes.Buffer
	_ = xml.EscapeText(&escaped, []byte(value))
	return escaped.String()
}
