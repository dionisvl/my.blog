package repository

import (
	"context"
	"database/sql"
	"errors"
	"time"

	"github.com/uptrace/bun"

	"api-go/internal/model"
)

type AphorismRepository interface {
	FindDaily(ctx context.Context, postID int, locale string) (*model.Aphorism, error)
}

type aphorismRepository struct {
	db *bun.DB
}

func NewAphorismRepository(db *bun.DB) AphorismRepository {
	return &aphorismRepository{db: db}
}

// quoteDay counts UTC calendar days since the epoch.
func quoteDay(now time.Time) int64 {
	return now.UTC().Unix() / 86400
}

func (r *aphorismRepository) FindDaily(ctx context.Context, postID int, locale string) (*model.Aphorism, error) {
	return r.findDailyAt(ctx, postID, locale, time.Now())
}

// quoteSlot picks a position in the ordered list of translated quotes:
// one step forward per UTC day, offset by article ID.
func quoteSlot(now time.Time, postID int, size int64) int64 {
	return (int64(postID) + quoteDay(now)) % size
}

const eligibleQuote = `btrim(detail_text_ru) <> '' AND btrim(detail_text_en) <> ''`

func (r *aphorismRepository) findDailyAt(ctx context.Context, postID int, locale string, now time.Time) (*model.Aphorism, error) {
	total, err := r.db.NewSelect().Model((*model.Aphorism)(nil)).Where(eligibleQuote).Count(ctx)
	if err != nil {
		return nil, err
	}
	if total == 0 {
		return nil, nil
	}

	quote := new(model.Aphorism)
	err = r.db.NewSelect().Model(quote).
		Column("id", "detail_text_ru", "detail_text_en").
		Where(eligibleQuote).
		OrderExpr("id").
		Offset(quoteSlot(now, postID, int64(total))).
		Limit(1).
		Scan(ctx)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	quote.Text = quote.DetailTextEN
	if locale == model.LocaleRU {
		quote.Text = quote.DetailTextRU
	}
	return quote, nil
}
