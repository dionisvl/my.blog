package repository

import (
	"context"
	"time"

	"github.com/uptrace/bun"

	"api-go/internal/model"
)

type IncomingRepository interface {
	Create(ctx context.Context, name, email, phone, message string) (*model.Incoming, error)
}

type incomingRepository struct {
	db *bun.DB
}

func NewIncomingRepository(db *bun.DB) IncomingRepository {
	return &incomingRepository{db: db}
}

func (r *incomingRepository) Create(ctx context.Context, name, email, phone, message string) (*model.Incoming, error) {
	now := time.Now()
	incoming := &model.Incoming{
		Name:      name,
		Email:     email,
		Phone:     phone,
		Message:   message,
		Status:    0,
		CreatedAt: now,
		UpdatedAt: now,
	}
	_, err := r.db.NewInsert().Model(incoming).Exec(ctx)
	if err != nil {
		return nil, err
	}
	return incoming, nil
}
