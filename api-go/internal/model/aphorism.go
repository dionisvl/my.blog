package model

import (
	"time"

	"github.com/uptrace/bun"
)

type Aphorism struct {
	bun.BaseModel `bun:"table:aphorism,alias:a"`

	ID           int       `bun:"id,pk,autoincrement" json:"id"`
	DetailTextRU string    `bun:"detail_text_ru,notnull" json:"-"`
	DetailTextEN string    `bun:"detail_text_en"         json:"-"`
	CreatedAt    time.Time `bun:"created_at,notnull"     json:"-"`
	UpdatedAt    time.Time `bun:"updated_at,notnull"     json:"-"`

	// Text is the quote in the requested locale; it is not a column.
	Text string `bun:"-" json:"text"`
}
