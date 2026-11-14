package model

import (
	"time"

	"github.com/uptrace/bun"
)

type Tag struct {
	bun.BaseModel `bun:"table:tags,alias:t"`

	ID        int       `bun:"id,pk,autoincrement" json:"id"`
	Title     string    `bun:"title,notnull"       json:"title"`
	Slug      string    `bun:"slug,notnull"        json:"slug"`
	CreatedAt time.Time `bun:"created_at,notnull"  json:"created_at"`
	UpdatedAt time.Time `bun:"updated_at,notnull"  json:"updated_at"`
}
