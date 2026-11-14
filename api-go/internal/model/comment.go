package model

import (
	"time"

	"github.com/uptrace/bun"
)

type Comment struct {
	bun.BaseModel `bun:"table:comments,alias:cm"`

	ID         int       `bun:"id,pk,autoincrement" json:"id"`
	AuthorName string    `bun:"author_name,notnull" json:"author_name"`
	Text       string    `bun:"text,notnull"        json:"text"`
	PostID     int       `bun:"post_id,notnull"     json:"-"`
	UserID     *int      `bun:"user_id"             json:"-"`
	Status     int       `bun:"status,notnull"      json:"-"`
	CreatedAt  time.Time `bun:"created_at,notnull"  json:"created_at"`
	UpdatedAt  time.Time `bun:"updated_at,notnull"  json:"updated_at"`
}
