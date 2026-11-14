package repository

import (
	"context"

	"github.com/uptrace/bun"

	"api-go/internal/model"
)

type TagRepository interface {
	FindBySlug(ctx context.Context, slug string) (*model.Tag, error)
}

type tagRepository struct {
	db *bun.DB
}

func NewTagRepository(db *bun.DB) TagRepository {
	return &tagRepository{db: db}
}

func (r *tagRepository) FindBySlug(ctx context.Context, slug string) (*model.Tag, error) {
	var tag model.Tag
	err := r.db.NewSelect().
		Model(&tag).
		Where("slug = ?", slug).
		Scan(ctx)
	if err != nil {
		return nil, err
	}
	return &tag, nil
}
