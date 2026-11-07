package repository

import (
	"context"

	"github.com/uptrace/bun"

	"api-go/internal/model"
)

type CategoryRepository interface {
	FindWithPostCounts(ctx context.Context) ([]model.CategoryWithCount, error)
	FindBySlug(ctx context.Context, slug string) (*model.Category, error)
}

type categoryRepository struct {
	db *bun.DB
}

func NewCategoryRepository(db *bun.DB) CategoryRepository {
	return &categoryRepository{db: db}
}

func (r *categoryRepository) FindWithPostCounts(ctx context.Context) ([]model.CategoryWithCount, error) {
	var results []model.CategoryWithCount
	err := r.db.NewSelect().
		TableExpr("categories AS c").
		ColumnExpr("c.*").
		ColumnExpr("COUNT(p.id) AS posts_count").
		Join("LEFT JOIN posts AS p ON p.category_id = c.id AND p.status = ? AND "+publishedVisibilityExpr, false).
		GroupExpr("c.id").
		Having("COUNT(p.id) > 0").
		OrderExpr("posts_count DESC").
		Scan(ctx, &results)
	if err != nil {
		return nil, err
	}
	return results, nil
}

func (r *categoryRepository) FindBySlug(ctx context.Context, slug string) (*model.Category, error) {
	var category model.Category
	err := r.db.NewSelect().
		Model(&category).
		Where("slug = ?", slug).
		Scan(ctx)
	if err != nil {
		return nil, err
	}
	return &category, nil
}