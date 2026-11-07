package repository

import (
	"context"
	"html"
	"net/url"
	"strings"
	"time"

	"github.com/uptrace/bun"

	"api-go/internal/model"
)

type PostRepository interface {
	FindPublishedPaginated(ctx context.Context, page, perPage int) ([]model.Post, int, error)
	FindPublishedBySlug(ctx context.Context, slug string) (*model.Post, error)
	FindFeatured(ctx context.Context, limit int) ([]model.Post, error)
	FindRecentPublished(ctx context.Context, limit int) ([]model.Post, error)
	FindPublishedByCategorySlugPaginated(ctx context.Context, slug string, page, perPage int) ([]model.Post, int, error)
	FindPublishedByTagSlugPaginated(ctx context.Context, slug string, page, perPage int) ([]model.Post, int, error)
	SearchPublished(ctx context.Context, query string, limit int) ([]model.Post, error)
	IncrementViews(ctx context.Context, postID int) (int, error)
}

type postRepository struct {
	db *bun.DB
}

func NewPostRepository(db *bun.DB) PostRepository {
	return &postRepository{db: db}
}

type preferredLocaleKey struct{}

// WithPreferredLocale makes list queries pick the card (title and summary) of the given published
// locale before falling back to the article's primary locale.
func WithPreferredLocale(ctx context.Context, locale string) context.Context {
	return context.WithValue(ctx, preferredLocaleKey{}, locale)
}

func (r *postRepository) FindPublishedPaginated(ctx context.Context, page, perPage int) ([]model.Post, int, error) {
	var posts []model.Post
	count, err := r.db.NewSelect().
		Model(&posts).
		Relation("Author").
		Relation("Category").
		Relation("Tags").
		Where("p.status = ?", false).
		Where(publishedVisibilityExpr).
		OrderExpr("p.created_at DESC").
		Limit(perPage).
		Offset((page - 1) * perPage).
		ScanAndCount(ctx)
	if err != nil {
		return nil, 0, err
	}
	resolveImages(posts)
	if err := r.attachDerivedFields(ctx, posts); err != nil {
		return nil, 0, err
	}
	return posts, count, nil
}

func (r *postRepository) FindPublishedBySlug(ctx context.Context, slug string) (*model.Post, error) {
	var post model.Post
	err := r.db.NewSelect().
		Model(&post).
		Relation("Author").
		Relation("Category").
		Relation("Tags").
		Where("p.id = "+anchorIDExpr, slug).
		Scan(ctx)
	if err != nil {
		return nil, err
	}

	comments, err := r.findApprovedComments(ctx, post.ID)
	if err != nil {
		return nil, err
	}
	post.Comments = comments

	likesCount, err := r.countLikes(ctx, post.ID)
	if err != nil {
		return nil, err
	}
	post.LikesCount = likesCount

	post.ResolveImageURL()

	single := []model.Post{post}
	if err := r.attachLocales(ctx, single); err != nil {
		return nil, err
	}
	post = single[0]

	return &post, nil
}

func (r *postRepository) FindFeatured(ctx context.Context, limit int) ([]model.Post, error) {
	var posts []model.Post
	err := r.db.NewSelect().
		Model(&posts).
		Relation("Author").
		Relation("Category").
		Where("p.is_featured = ?", true).
		Where("p.status = ?", false).
		Where(publishedVisibilityExpr).
		Limit(limit).
		Scan(ctx)
	if err != nil {
		return nil, err
	}
	resolveImages(posts)
	if err := r.attachDerivedFields(ctx, posts); err != nil {
		return nil, err
	}
	return posts, nil
}

func (r *postRepository) FindRecentPublished(ctx context.Context, limit int) ([]model.Post, error) {
	var posts []model.Post
	err := r.db.NewSelect().
		Model(&posts).
		Relation("Author").
		Relation("Category").
		Where("p.status = ?", false).
		Where(publishedVisibilityExpr).
		OrderExpr("p.created_at DESC").
		Limit(limit).
		Scan(ctx)
	if err != nil {
		return nil, err
	}
	resolveImages(posts)
	if err := r.attachDerivedFields(ctx, posts); err != nil {
		return nil, err
	}
	return posts, nil
}

func (r *postRepository) FindPublishedByCategorySlugPaginated(ctx context.Context, slug string, page, perPage int) ([]model.Post, int, error) {
	var posts []model.Post
	count, err := r.db.NewSelect().
		Model(&posts).
		Relation("Author").
		Relation("Category").
		Relation("Tags").
		Join("JOIN categories AS cat ON cat.id = p.category_id").
		Where("cat.slug = ?", slug).
		Where("p.status = ?", false).
		Where(publishedVisibilityExpr).
		OrderExpr("p.created_at DESC").
		Limit(perPage).
		Offset((page - 1) * perPage).
		ScanAndCount(ctx)
	if err != nil {
		return nil, 0, err
	}
	resolveImages(posts)
	if err := r.attachDerivedFields(ctx, posts); err != nil {
		return nil, 0, err
	}
	return posts, count, nil
}

func (r *postRepository) FindPublishedByTagSlugPaginated(ctx context.Context, slug string, page, perPage int) ([]model.Post, int, error) {
	var posts []model.Post
	count, err := r.db.NewSelect().
		Model(&posts).
		Relation("Author").
		Relation("Category").
		Relation("Tags").
		Join("JOIN post_tags AS pt ON pt.post_id = p.id").
		Join("JOIN tags AS tg ON tg.id = pt.tag_id").
		Where("tg.slug = ?", slug).
		Where("p.status = ?", false).
		Where(publishedVisibilityExpr).
		OrderExpr("p.created_at DESC").
		Limit(perPage).
		Offset((page - 1) * perPage).
		ScanAndCount(ctx)
	if err != nil {
		return nil, 0, err
	}
	resolveImages(posts)
	if err := r.attachDerivedFields(ctx, posts); err != nil {
		return nil, 0, err
	}
	return posts, count, nil
}

func (r *postRepository) SearchPublished(ctx context.Context, query string, limit int) ([]model.Post, error) {
	escaped := escapeLike(query)
	pattern := "%" + escaped + "%"

	var posts []model.Post
	err := r.db.NewSelect().
		Model(&posts).
		Relation("Author").
		Relation("Category").
		Where("p.status = ?", false).
		Where(publishedVisibilityExpr).
		WhereGroup(" AND ", func(q *bun.SelectQuery) *bun.SelectQuery {
			return q.
				Where("p.title ILIKE ?", pattern).
				WhereOr("p.description ILIKE ?", pattern).
				WhereOr("p.content ILIKE ?", pattern).
				// Modular articles: search the text of PUBLISHED locales only.
				WhereOr(publishedLocaleSearchExpr, pattern, pattern)
		}).
		OrderExpr("p.created_at DESC").
		Limit(limit).
		Scan(ctx)
	if err != nil {
		return nil, err
	}
	resolveImages(posts)
	if err := r.attachDerivedFields(ctx, posts); err != nil {
		return nil, err
	}
	return posts, nil
}

func (r *postRepository) IncrementViews(ctx context.Context, postID int) (int, error) {
	var viewsCount int
	err := r.db.NewRaw("UPDATE posts SET views_count = views_count + 1 WHERE id = ? RETURNING views_count", postID).Scan(ctx, &viewsCount)
	return viewsCount, err
}

func (r *postRepository) findApprovedComments(ctx context.Context, postID int) ([]model.Comment, error) {
	var comments []model.Comment
	err := r.db.NewSelect().
		Model(&comments).
		Where("post_id = ?", postID).
		Where("status = ?", 1).
		OrderExpr("created_at ASC").
		Scan(ctx)
	if err != nil {
		return nil, err
	}
	return comments, nil
}

func (r *postRepository) countLikes(ctx context.Context, postID int) (int, error) {
	count, err := r.db.NewSelect().
		TableExpr("posts_likes").
		Where("post_id = ?", postID).
		Count(ctx)
	return count, err
}

func (r *postRepository) attachLikeCounts(ctx context.Context, posts []model.Post) error {
	if len(posts) == 0 {
		return nil
	}

	postIDs := make([]int, len(posts))
	for i, post := range posts {
		postIDs[i] = post.ID
	}

	var results []struct {
		PostID int `bun:"post_id"`
		Count  int `bun:"count"`
	}

	err := r.db.NewSelect().
		TableExpr("posts_likes").
		Column("post_id").
		ColumnExpr("COUNT(*) as count").
		Where("post_id IN (?)", bun.List(postIDs)).
		Group("post_id").
		Scan(ctx, &results)
	if err != nil {
		return err
	}

	countMap := make(map[int]int)
	for _, result := range results {
		countMap[result.PostID] = result.Count
	}

	for i := range posts {
		posts[i].LikesCount = countMap[posts[i].ID]
	}

	return nil
}

// publishedVisibilityExpr is the second visibility guard next to `p.status = false`.
//
// posts.status is inverted (false = published) and a brand new row can end up published by default,
// so a modular anchor is public only when at least one of its locales is published.
// Legacy rows are unaffected. Every public query on posts must apply it with alias `p`.
const publishedVisibilityExpr = `(p.route_mode = 'legacy_canonical' OR EXISTS (` +
	`SELECT 1 FROM post_locales AS vis WHERE vis.post_id = p.id AND vis.published_at IS NOT NULL))`

// anchorIDExpr selects the anchor of a public slug: the lowest id among the rows ResolveBySlug can publish.
// Legacy slugs may be duplicated, so every reader of a slug (resolver, locale, detail) must pick this same row;
// otherwise a later duplicate's translation is served with the metadata of another post. The single `?` is the slug.
const anchorIDExpr = `(SELECT p.id FROM posts AS p WHERE p.slug = ? AND p.status = FALSE AND ` +
	publishedVisibilityExpr + ` ORDER BY p.id ASC LIMIT 1)`

// publishedLocaleSearchExpr matches title/search_text of the PUBLISHED locales of a modular article.
const publishedLocaleSearchExpr = `EXISTS (SELECT 1 FROM post_locales AS sl ` +
	`WHERE sl.post_id = p.id AND sl.published_at IS NOT NULL AND (sl.title ILIKE ? OR sl.search_text ILIKE ?))`

type publishedLocaleRow struct {
	PostID      int       `bun:"post_id"`
	Locale      string    `bun:"locale"`
	PublishedAt time.Time `bun:"published_at"`
	Title       string    `bun:"title"`
	Summary     string    `bun:"summary"`
}

// attachDerivedFields fills the non-column fields shared by every list/detail query.
func (r *postRepository) attachDerivedFields(ctx context.Context, posts []model.Post) error {
	if err := r.attachLikeCounts(ctx, posts); err != nil {
		return err
	}
	return r.attachLocales(ctx, posts)
}

// attachLocales loads the PUBLISHED locales of modular articles and lets the card fields follow the
// published locale (primary locale first): the title and the HTML-escaped plain-text summary.
// posts.description is rendered as raw HTML by the frontend, hence the escaping.
func (r *postRepository) attachLocales(ctx context.Context, posts []model.Post) error {
	var ids []int
	for i := range posts {
		if posts[i].IsModular() || posts[i].OriginalLocale != nil {
			ids = append(ids, posts[i].ID)
		}
	}
	if len(ids) == 0 {
		return nil
	}

	byPost, err := loadPublishedLocales(ctx, r.db, ids)
	if err != nil {
		return err
	}

	for i := range posts {
		rows := byPost[posts[i].ID]
		if !posts[i].IsModular() {
			if posts[i].OriginalLocale != nil {
				posts[i].Locales = legacyLocaleLinks(posts[i].Slug, posts[i].Title, posts[i].CreatedAt, *posts[i].OriginalLocale, rows)
			}
			continue
		}
		if len(rows) == 0 {
			continue
		}

		card := rows[0]
		preferred, _ := ctx.Value(preferredLocaleKey{}).(string)
		for _, row := range rows {
			if preferred != "" && row.Locale == preferred {
				card = row
				break
			}
			if posts[i].PrimaryLocale != nil && row.Locale == *posts[i].PrimaryLocale {
				card = row
			}
		}

		posts[i].Locales = localeLinks(posts[i].Slug, rows)
		posts[i].Title = card.Title
		escaped := html.EscapeString(card.Summary)
		posts[i].Description = &escaped
	}

	return nil
}

// loadPublishedLocales returns, per post id, the published locales ordered by publication time.
func loadPublishedLocales(ctx context.Context, db *bun.DB, postIDs []int) (map[int][]publishedLocaleRow, error) {
	var rows []publishedLocaleRow
	err := db.NewSelect().
		TableExpr("post_locales AS pl").
		ColumnExpr("pl.post_id AS post_id, pl.locale AS locale, pl.published_at AS published_at").
		ColumnExpr("pl.title AS title, pl.summary AS summary").
		Where("pl.post_id IN (?)", bun.List(postIDs)).
		Where("pl.published_at IS NOT NULL").
		OrderExpr("pl.post_id ASC, pl.published_at ASC, pl.locale ASC").
		Scan(ctx, &rows)
	if err != nil {
		return nil, err
	}

	byPost := make(map[int][]publishedLocaleRow)
	for _, row := range rows {
		byPost[row.PostID] = append(byPost[row.PostID], row)
	}
	return byPost, nil
}

func localeLinks(slug string, rows []publishedLocaleRow) []model.LocaleLink {
	links := make([]model.LocaleLink, 0, len(rows))
	for _, row := range rows {
		links = append(links, model.LocaleLink{
			Locale:      row.Locale,
			URL:         "/" + row.Locale + "/post/" + url.PathEscape(slug),
			Title:       row.Title,
			PublishedAt: row.PublishedAt,
		})
	}
	return links
}

func legacyLocaleLinks(slug, title string, createdAt time.Time, originalLocale string, rows []publishedLocaleRow) []model.LocaleLink {
	links := []model.LocaleLink{{
		Locale: originalLocale, URL: "/post/" + url.PathEscape(slug), Title: title, PublishedAt: createdAt,
	}}
	for _, row := range rows {
		if row.Locale != originalLocale {
			links = append(links, localeLinks(slug, []publishedLocaleRow{row})...)
		}
	}
	return links
}

func escapeLike(s string) string {
	s = strings.ReplaceAll(s, `\`, `\\`)
	s = strings.ReplaceAll(s, `%`, `\%`)
	s = strings.ReplaceAll(s, `_`, `\_`)
	return s
}

func resolveImages(posts []model.Post) {
	for i := range posts {
		posts[i].ResolveImageURL()
	}
}
