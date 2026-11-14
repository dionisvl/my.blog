package repository

import (
	"context"
	"math/rand/v2"

	"github.com/uptrace/bun"

	"api-go/internal/model"
)

type AphorismRepository interface {
	FindRandom(ctx context.Context) (*model.Aphorism, error)
}

type aphorismRepository struct {
	db *bun.DB
}

func NewAphorismRepository(db *bun.DB) AphorismRepository {
	return &aphorismRepository{db: db}
}

func (r *aphorismRepository) FindRandom(ctx context.Context) (*model.Aphorism, error) {
	count, err := r.db.NewSelect().Model((*model.Aphorism)(nil)).Count(ctx)
	if err != nil || count == 0 {
		return nil, err
	}

	offset := rand.IntN(count)

	var aphorism model.Aphorism
	err = r.db.NewSelect().
		Model(&aphorism).
		OrderExpr("id ASC").
		Limit(1).
		Offset(offset).
		Scan(ctx)
	if err != nil {
		return nil, err
	}
	return &aphorism, nil
}
