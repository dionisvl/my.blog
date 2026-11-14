package model

import (
	"time"

	"github.com/uptrace/bun"
)

type Incoming struct {
	bun.BaseModel `bun:"table:incomings,alias:inc"`

	ID        int       `bun:"id,pk,autoincrement" json:"id"`
	Name      string    `bun:"name,notnull"        json:"name"`
	Email     string    `bun:"email"               json:"email"`
	Phone     string    `bun:"phone"               json:"phone"`
	Message   string    `bun:"message,notnull"     json:"message"`
	Status    int       `bun:"status,notnull"      json:"-"`
	CreatedAt time.Time `bun:"created_at,notnull"  json:"created_at"`
	UpdatedAt time.Time `bun:"updated_at,notnull"  json:"updated_at"`
}
