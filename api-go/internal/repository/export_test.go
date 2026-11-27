package repository

import (
	"context"
	"time"

	"api-go/internal/model"
)

// FindDailyAt lets tests pin the clock.
func FindDailyAt(r AphorismRepository, ctx context.Context, postID int, locale string, now time.Time) (*model.Aphorism, error) {
	return r.(*aphorismRepository).findDailyAt(ctx, postID, locale, now)
}
