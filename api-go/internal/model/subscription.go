package model

import (
	"time"

	"github.com/uptrace/bun"
)

type Subscription struct {
	bun.BaseModel `bun:"table:subscriptions,alias:sub"`

	ID        int       `bun:"id,pk,autoincrement" json:"id"`
	Email     string    `bun:"email,notnull"       json:"email"`
	Token     string    `bun:"token"               json:"-"`
	CreatedAt time.Time `bun:"created_at,notnull"  json:"created_at"`
	UpdatedAt time.Time `bun:"updated_at,notnull"  json:"updated_at"`
}
