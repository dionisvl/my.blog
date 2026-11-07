package model

import (
	"encoding/json"
	"time"
)

const (
	// ContractVersion is the version of the JSON response contract of the article endpoints
	// (see api-symfony/contracts/article-blocks/v1/CONTRACT.md).
	ContractVersion = 1

	RouteModeLegacy  = "legacy_canonical"
	RouteModeNeutral = "neutral"

	LocaleRU = "ru"
	LocaleEN = "en"
)

// IsSupportedLocale reports whether locale is one of the explicit article locales.
func IsSupportedLocale(locale string) bool {
	return locale == LocaleRU || locale == LocaleEN
}

// LocaleLink is one currently PUBLISHED locale of a modular article.
type LocaleLink struct {
	Locale      string    `json:"locale"`
	URL         string    `json:"url"`
	Title       string    `json:"title"`
	PublishedAt time.Time `json:"published_at"`
}

// PublishedSitemapLocale is one currently published locale of a neutral article.
type PublishedSitemapLocale struct {
	Slug        string    `bun:"slug"`
	Locale      string    `bun:"locale"`
	PublishedAt time.Time `bun:"published_at"`
}

// ArticleResolution answers "what is this slug and where does it live" without side effects:
// no view counter, no cookie, no comments. Astro uses it for the neutral /post/{slug} entry.
type ArticleResolution struct {
	ContractVersion int          `json:"contract_version"`
	Slug            string       `json:"slug"`
	RouteMode       string       `json:"route_mode"`
	OriginalLocale  *string      `json:"original_locale"`
	PrimaryLocale   *string      `json:"primary_locale"`
	CanonicalPath   *string      `json:"canonical_path"`
	Locales         []LocaleLink `json:"locales"`
	XDefaultLocale  *string      `json:"x_default_locale"`
}

// PublishedLocale is the content of one published locale.
// It is built only from post_locales rows with published_at set; drafts are unreachable.
type PublishedLocale struct {
	Locale          string          `json:"locale"`
	SchemaVersion   int             `json:"schema_version"`
	PublishedAt     time.Time       `json:"published_at"`
	Title           string          `json:"title"`
	Summary         string          `json:"summary"`
	MetaTitle       *string         `json:"meta_title"`
	MetaDescription *string         `json:"meta_description"`
	Document        json.RawMessage `json:"document"`
}
