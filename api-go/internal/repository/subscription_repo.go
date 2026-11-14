package repository

import (
	"context"
	"time"

	"github.com/uptrace/bun"

	"api-go/internal/model"
)

type SubscriptionRepository interface {
	Create(ctx context.Context, email, token string) (*model.Subscription, error)
	EmailExists(ctx context.Context, email string) (bool, error)
}

type subscriptionRepository struct {
	db *bun.DB
}

func NewSubscriptionRepository(db *bun.DB) SubscriptionRepository {
	return &subscriptionRepository{db: db}
}

func (r *subscriptionRepository) Create(ctx context.Context, email, token string) (*model.Subscription, error) {
	now := time.Now()
	sub := &model.Subscription{
		Email:     email,
		Token:     token,
		CreatedAt: now,
		UpdatedAt: now,
	}
	_, err := r.db.NewInsert().
		Model(sub).
		On("CONFLICT (email) DO NOTHING").
		Exec(ctx)
	if err != nil {
		return nil, err
	}
	return sub, nil
}

func (r *subscriptionRepository) EmailExists(ctx context.Context, email string) (bool, error) {
	return r.db.NewSelect().
		TableExpr("subscriptions").
		Where("email = ?", email).
		Exists(ctx)
}
