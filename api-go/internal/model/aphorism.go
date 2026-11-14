package model

import (
	"time"

	"github.com/uptrace/bun"
)

type Aphorism struct {
	bun.BaseModel `bun:"table:aphorism,alias:a"`

	ID         int       `bun:"id,pk,autoincrement" json:"id"`
	DetailText string    `bun:"detail_text,notnull" json:"text"`
	CreatedAt  time.Time `bun:"created_at,notnull"  json:"-"`
	UpdatedAt  time.Time `bun:"updated_at,notnull"  json:"-"`
}
