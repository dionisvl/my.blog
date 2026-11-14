package model

import (
	"time"

	"github.com/uptrace/bun"
)

type Category struct {
	bun.BaseModel `bun:"table:categories,alias:c"`

	ID          int       `bun:"id,pk,autoincrement" json:"id"`
	Title       string    `bun:"title,notnull"       json:"title"`
	Slug        string    `bun:"slug,notnull"        json:"slug"`
	PreviewText *string   `bun:"preview_text"        json:"preview_text,omitempty"`
	DetailText  *string   `bun:"detail_text"         json:"detail_text,omitempty"`
	CreatedAt   time.Time `bun:"created_at,notnull"  json:"created_at"`
	UpdatedAt   time.Time `bun:"updated_at,notnull"  json:"updated_at"`
}

type CategoryWithCount struct {
	Category
	PostsCount int `bun:"posts_count" json:"posts_count"`
}
