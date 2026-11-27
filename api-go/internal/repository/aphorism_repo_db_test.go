package repository_test

import (
	"context"
	"strings"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"api-go/internal/model"
	"api-go/internal/repository"
	"api-go/internal/repository/testhelper"
)

func TestAphorismRepository_FindDaily(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	tdb.Truncate(t, "aphorism")
	ctx := context.Background()
	repo := repository.NewAphorismRepository(tdb.DB)
	now := time.Date(2026, time.November, 27, 12, 0, 0, 0, time.UTC)

	quote, err := repository.FindDailyAt(repo, ctx, 1, model.LocaleEN, now)
	require.NoError(t, err)
	assert.Nil(t, quote, "empty table must not fail")

	_, err = tdb.SQLDB().ExecContext(ctx, `
		INSERT INTO aphorism (id, detail_text_ru, detail_text_en) VALUES
		  (10, 'ru-10', 'en-10'),
		  (20, 'ru-20', 'en-20'),
		  (30, 'ru-30', 'en-30'),
		  (40, 'ru-40', NULL),
		  (50, 'ru-50', '   ')`)
	require.NoError(t, err)

	got := func(postID int, locale string) *model.Aphorism {
		q, err := repository.FindDailyAt(repo, ctx, postID, locale, now)
		require.NoError(t, err)
		require.NotNil(t, q)
		return q
	}

	first := got(0, model.LocaleEN)
	order := []int{10, 20, 30}
	base := -1
	for i, id := range order {
		if id == first.ID {
			base = i
		}
	}
	require.GreaterOrEqual(t, base, 0, "only rows with English text are eligible")
	assert.True(t, strings.HasPrefix(first.Text, "en-"))
	assert.True(t, strings.HasPrefix(got(0, model.LocaleRU).Text, "ru-"))

	assert.Equal(t, order[(base+1)%3], got(1, model.LocaleEN).ID, "next article moves one step")
	assert.Equal(t, order[(base+2)%3], got(2, model.LocaleEN).ID)
	assert.Equal(t, first.ID, got(3, model.LocaleEN).ID, "rotation wraps")

	tomorrow, err := repository.FindDailyAt(repo, ctx, 0, model.LocaleEN, now.AddDate(0, 0, 1))
	require.NoError(t, err)
	assert.Equal(t, order[(base+1)%3], tomorrow.ID, "next day moves one step")
}
