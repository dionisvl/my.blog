package model

import (
	"time"

	"github.com/uptrace/bun"
)

type User struct {
	bun.BaseModel `bun:"table:users,alias:u"`

	ID        int       `bun:"id,pk,autoincrement" json:"id"`
	Name      string    `bun:"name,notnull"        json:"name"`
	Email     string    `bun:"email,notnull"       json:"-"`
	IsAdmin   bool      `bun:"is_admin,notnull"    json:"-"`
	CreatedAt time.Time `bun:"created_at,notnull"  json:"created_at"`
	UpdatedAt time.Time `bun:"updated_at,notnull"  json:"updated_at"`
}
