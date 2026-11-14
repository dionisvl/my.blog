package model

import (
	"strings"
	"time"

	"github.com/uptrace/bun"
)

type Post struct {
	bun.BaseModel `bun:"table:posts,alias:p"`

	ID          int       `bun:"id,pk,autoincrement" json:"id"`
	Title       string    `bun:"title,notnull"       json:"title"`
	Slug        string    `bun:"slug,notnull"        json:"slug"`
	Content     *string   `bun:"content"             json:"content,omitempty"`
	Description *string   `bun:"description"         json:"description,omitempty"`
	Image       *string   `bun:"image"               json:"-"`
	Status      bool      `bun:"status,notnull"      json:"-"`
	IsFeatured  bool      `bun:"is_featured,notnull" json:"is_featured"`
	ViewsCount  int       `bun:"views_count,notnull" json:"views_count"`
	UserID      int       `bun:"user_id,notnull"     json:"-"`
	CategoryID  *int      `bun:"category_id"         json:"-"`
	CreatedAt   time.Time `bun:"created_at,notnull"  json:"created_at"`
	UpdatedAt   time.Time `bun:"updated_at,notnull"  json:"updated_at"`

	// Additive fields (modular articles). Legacy rows keep route_mode=legacy_canonical and no locales.
	RouteMode      string  `bun:"route_mode,notnull" json:"route_mode"`
	OriginalLocale *string `bun:"original_locale"    json:"original_locale,omitempty"`
	PrimaryLocale  *string `bun:"primary_locale"     json:"primary_locale,omitempty"`

	Author   *User     `bun:"rel:belongs-to,join:user_id=id"     json:"author,omitempty"`
	Category *Category `bun:"rel:belongs-to,join:category_id=id" json:"category,omitempty"`
	Tags     []Tag     `bun:"m2m:post_tags,join:Post=Tag"        json:"tags,omitempty"`
	Comments []Comment `bun:"-"                                  json:"comments,omitempty"`

	ImageURL   string `bun:"-" json:"image_url"`
	LikesCount int    `bun:"-" json:"likes_count"`

	// Locales lists the published locales of a modular article (empty for legacy posts).
	Locales []LocaleLink `bun:"-" json:"locales,omitempty"`
}

// IsModular reports whether the row is the anchor of a modular multilingual article.
func (p *Post) IsModular() bool {
	return p.RouteMode == RouteModeNeutral
}

// MediaBaseURL is the public prefix of media files; set once at start-up from MEDIA_BASE_URL.
var MediaBaseURL = "/storage"

// MediaURL joins the configured base URL and a storage key such as "uploads/x.webp".
func MediaURL(key string) string {
	return strings.TrimRight(MediaBaseURL, "/") + "/" + key
}

func (p *Post) ResolveImageURL() {
	if p.Image == nil || *p.Image == "" {
		p.ImageURL = MediaURL("blog_images/no-image.png")
	} else {
		p.ImageURL = MediaURL("uploads/" + *p.Image)
	}
}

type PostTag struct {
	bun.BaseModel `bun:"table:post_tags"`

	PostID int  `bun:"post_id"`
	TagID  int  `bun:"tag_id"`
	Post   Post `bun:"rel:belongs-to,join:post_id=id"`
	Tag    Tag  `bun:"rel:belongs-to,join:tag_id=id"`
}
