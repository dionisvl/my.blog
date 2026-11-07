package repository_test

import (
	"context"
	"fmt"
	"testing"
	"time"

	"github.com/stretchr/testify/require"

	"api-go/internal/repository/testhelper"
)

// articleDoc returns a minimal valid article document for the given locale.
func articleDoc(locale string) string {
	return fmt.Sprintf(`{"schema_version":1,"locale":%q,"blocks":[{"id":"body","type":"richText","props":{"html":"<p>x</p>"}}]}`, locale)
}

// insertAnchor inserts a modular anchor the way the Symfony publisher does: as an explicit draft
// (posts.status = TRUE is the inverted "draft" flag).
func insertAnchor(t *testing.T, tdb *testhelper.TestDB, slug, primaryLocale string, userID int, categoryID *int) int {
	t.Helper()

	var id int
	err := tdb.SQLDB().QueryRowContext(context.Background(),
		`INSERT INTO posts (title, slug, content, description, status, is_featured, views_count, user_id, category_id, route_mode, primary_locale, created_at, updated_at)
		 VALUES ($1, $2, '', '', TRUE, FALSE, 0, $3, $4, 'neutral', $5, $6, $6) RETURNING id`,
		"Working "+slug, slug, userID, categoryID, primaryLocale, time.Now(),
	).Scan(&id)
	require.NoError(t, err)
	return id
}

// insertLocale stores (or replaces) the content of one locale as an unpublished row.
func insertLocale(t *testing.T, tdb *testhelper.TestDB, postID int, locale, title, summary, searchText, document string) {
	t.Helper()

	_, err := tdb.SQLDB().ExecContext(context.Background(),
		`INSERT INTO post_locales (post_id, locale, schema_version, title, summary, search_text, document)
		 VALUES ($1, $2, 1, $3, $4, $5, $6::jsonb)
		 ON CONFLICT (post_id, locale) DO UPDATE
		 SET title = EXCLUDED.title, summary = EXCLUDED.summary,
		     search_text = EXCLUDED.search_text, document = EXCLUDED.document`,
		postID, locale, title, summary, searchText, document,
	)
	require.NoError(t, err)
}

// setLocalePublished publishes or unpublishes an existing post_locales row.
func setLocalePublished(t *testing.T, tdb *testhelper.TestDB, postID int, locale string, published bool) {
	t.Helper()

	var publishedAt *time.Time
	if published {
		now := time.Now()
		publishedAt = &now
	}

	res, err := tdb.SQLDB().ExecContext(context.Background(),
		`UPDATE post_locales SET published_at = $3 WHERE post_id = $1 AND locale = $2`,
		postID, locale, publishedAt,
	)
	require.NoError(t, err)
	n, err := res.RowsAffected()
	require.NoError(t, err)
	require.Equal(t, int64(1), n, "insertLocale must create the locale row first")
}

// publishLocale stores a locale, publishes it and makes the anchor visible, like
// ArticlePublisher::publish() does in one transaction.
func publishLocale(t *testing.T, tdb *testhelper.TestDB, postID int, locale, title, summary, searchText string) {
	t.Helper()

	insertLocale(t, tdb, postID, locale, title, summary, searchText, articleDoc(locale))
	setLocalePublished(t, tdb, postID, locale, true)

	_, err := tdb.SQLDB().ExecContext(context.Background(), `UPDATE posts SET status = FALSE WHERE id = $1`, postID)
	require.NoError(t, err)
}

func execSQL(tdb *testhelper.TestDB, query string, args ...any) error {
	_, err := tdb.SQLDB().ExecContext(context.Background(), query, args...)
	return err
}

func countRows(t *testing.T, tdb *testhelper.TestDB, table string) int {
	t.Helper()

	var n int
	require.NoError(t, tdb.SQLDB().QueryRowContext(context.Background(), "SELECT COUNT(*) FROM "+table).Scan(&n))
	return n
}
