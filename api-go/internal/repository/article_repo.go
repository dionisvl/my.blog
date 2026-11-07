package repository

import (
	"context"
	"encoding/json"
	"net/url"
	"time"

	"github.com/uptrace/bun"

	"api-go/internal/model"
)

// ArticleRepository is the read-only API over modular articles and legacy translations. It never writes (no view counters)
// and only ever reads post_locales rows whose published_at is set.
type ArticleRepository interface {
	// ResolveBySlug describes a published slug (legacy or modular) without side effects.
	// It returns sql.ErrNoRows for unknown slugs and for modular articles with no published locale.
	ResolveBySlug(ctx context.Context, slug string) (*model.ArticleResolution, error)
	// FindPublishedLocale returns a published modular locale or a classified legacy translation.
	// It returns sql.ErrNoRows when the slug is unknown, hidden, or the locale is not published.
	FindPublishedLocale(ctx context.Context, slug, locale string) (*model.PublishedLocale, error)
	// FindPublishedSitemapLocales returns only published variants of neutral articles.
	FindPublishedSitemapLocales(ctx context.Context) ([]model.PublishedSitemapLocale, error)
}

type articleRepository struct {
	db *bun.DB
}

func NewArticleRepository(db *bun.DB) ArticleRepository {
	return &articleRepository{db: db}
}

type anchorRow struct {
	ID             int        `bun:"id"`
	Slug           string     `bun:"slug"`
	Title          string     `bun:"title"`
	CreatedAt      *time.Time `bun:"created_at"`
	RouteMode      string     `bun:"route_mode"`
	OriginalLocale *string    `bun:"original_locale"`
	PrimaryLocale  *string    `bun:"primary_locale"`
}

type publishedLocaleDetailRow struct {
	Locale          string    `bun:"locale"`
	PublishedAt     time.Time `bun:"published_at"`
	SchemaVersion   int       `bun:"schema_version"`
	Title           string    `bun:"title"`
	Summary         string    `bun:"summary"`
	MetaTitle       *string   `bun:"meta_title"`
	MetaDescription *string   `bun:"meta_description"`
	Document        string    `bun:"document"`
}

func (r *articleRepository) ResolveBySlug(ctx context.Context, slug string) (*model.ArticleResolution, error) {
	var anchor anchorRow
	err := r.db.NewSelect().
		TableExpr("posts AS p").
		ColumnExpr("p.id AS id, p.slug AS slug, p.title AS title, p.created_at AS created_at, p.route_mode AS route_mode").
		ColumnExpr("p.original_locale AS original_locale, p.primary_locale AS primary_locale").
		Where("p.id = "+anchorIDExpr, slug).
		Scan(ctx, &anchor)
	if err != nil {
		return nil, err
	}

	resolution := &model.ArticleResolution{
		ContractVersion: model.ContractVersion,
		Slug:            anchor.Slug,
		RouteMode:       anchor.RouteMode,
		OriginalLocale:  anchor.OriginalLocale,
		PrimaryLocale:   anchor.PrimaryLocale,
		Locales:         []model.LocaleLink{},
	}

	if anchor.RouteMode == model.RouteModeLegacy {
		canonical := "/post/" + url.PathEscape(anchor.Slug)
		resolution.CanonicalPath = &canonical
		if anchor.OriginalLocale != nil {
			byPost, err := loadPublishedLocales(ctx, r.db, []int{anchor.ID})
			if err != nil {
				return nil, err
			}
			var createdAt time.Time
			if anchor.CreatedAt != nil {
				createdAt = *anchor.CreatedAt
			}
			resolution.Locales = legacyLocaleLinks(anchor.Slug, anchor.Title, createdAt, *anchor.OriginalLocale, byPost[anchor.ID])
		}
		return resolution, nil
	}

	byPost, err := loadPublishedLocales(ctx, r.db, []int{anchor.ID})
	if err != nil {
		return nil, err
	}

	rows := byPost[anchor.ID]
	resolution.Locales = localeLinks(anchor.Slug, rows)

	hasRU, hasEN := false, false
	for _, row := range rows {
		hasRU = hasRU || row.Locale == model.LocaleRU
		hasEN = hasEN || row.Locale == model.LocaleEN
	}
	if hasRU && hasEN {
		xDefault := model.LocaleEN
		resolution.XDefaultLocale = &xDefault
	}

	return resolution, nil
}

func (r *articleRepository) FindPublishedLocale(ctx context.Context, slug, locale string) (*model.PublishedLocale, error) {
	var row publishedLocaleDetailRow
	err := r.db.NewSelect().
		TableExpr("posts AS p").
		ColumnExpr("pl.locale AS locale, pl.published_at AS published_at").
		ColumnExpr("pl.schema_version AS schema_version").
		ColumnExpr("pl.title AS title, pl.summary AS summary").
		ColumnExpr("pl.meta_title AS meta_title, pl.meta_description AS meta_description").
		ColumnExpr("pl.document::text AS document").
		Join("JOIN post_locales AS pl ON pl.post_id = p.id").
		Where("pl.published_at IS NOT NULL").
		Where("p.id = "+anchorIDExpr, slug).
		Where("p.slug = ?", slug).
		Where("(p.route_mode = ? OR (p.route_mode = ? AND p.original_locale IS NOT NULL AND p.original_locale <> pl.locale))", model.RouteModeNeutral, model.RouteModeLegacy).
		Where("p.status = ?", false).
		Where("pl.locale = ?", locale).
		Limit(1).
		Scan(ctx, &row)
	if err != nil {
		return nil, err
	}

	return &model.PublishedLocale{
		Locale:          row.Locale,
		SchemaVersion:   row.SchemaVersion,
		PublishedAt:     row.PublishedAt,
		Title:           row.Title,
		Summary:         row.Summary,
		MetaTitle:       row.MetaTitle,
		MetaDescription: row.MetaDescription,
		Document:        json.RawMessage(row.Document),
	}, nil
}

func (r *articleRepository) FindPublishedSitemapLocales(ctx context.Context) ([]model.PublishedSitemapLocale, error) {
	var locales []model.PublishedSitemapLocale
	err := r.db.NewSelect().
		TableExpr("posts AS p").
		ColumnExpr("p.slug AS slug, pl.locale AS locale, pl.published_at AS published_at").
		Join("JOIN post_locales AS pl ON pl.post_id = p.id").
		Where("p.route_mode = ?", model.RouteModeNeutral).
		Where("p.status = ?", false).
		Where("pl.published_at IS NOT NULL").
		Where("pl.locale IN (?, ?)", model.LocaleRU, model.LocaleEN).
		OrderExpr("p.slug ASC, pl.locale ASC").
		Scan(ctx, &locales)
	if err != nil {
		return nil, err
	}
	return locales, nil
}
