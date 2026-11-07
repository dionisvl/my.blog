package repository_test

import (
	"context"
	"database/sql"
	"reflect"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"api-go/internal/model"
	"api-go/internal/repository/testhelper"
)

// TestMigration_AdditiveOnProductionBaseline restores the production schema snapshot, adds legacy data,
// applies the migration, checks nothing existing changed, then reverts and re-applies it.
func TestMigration_AdditiveOnProductionBaseline(t *testing.T) {
	tdb := testhelper.NewBareTestDB(t)
	ctx := context.Background()

	tdb.ApplyStatements(t, testhelper.BaselineStatements(t))

	// Legacy rows exactly as production has them: explicit status, no new columns.
	require.NoError(t, execSQL(tdb, `INSERT INTO users (name, email, is_admin) VALUES ('A', 'a@test.com', TRUE)`))
	require.NoError(t, execSQL(tdb,
		`INSERT INTO posts (title, slug, content, status, is_featured, user_id) VALUES
		 ('Published', 'published', 'body', FALSE, FALSE, 1),
		 ('Draft', 'draft', 'body', TRUE, FALSE, 1),
		 ('Duplicate A', 'dup', 'body', FALSE, FALSE, 1),
		 ('Duplicate B', 'dup', 'body', FALSE, FALSE, 1)`))
	require.NoError(t, execSQL(tdb, `INSERT INTO comments (text, user_id, post_id, status) VALUES ('hi', 1, 1, 1)`))
	require.NoError(t, execSQL(tdb, `INSERT INTO posts_likes (post_id, ip) VALUES (1, '203.0.113.7')`))

	type snapshot struct {
		ID     int
		Slug   string
		Status bool
	}
	readPosts := func() []snapshot {
		rows, err := tdb.SQLDB().QueryContext(ctx, `SELECT id, slug, status FROM posts ORDER BY id`)
		require.NoError(t, err)
		defer rows.Close()

		var out []snapshot
		for rows.Next() {
			var s snapshot
			require.NoError(t, rows.Scan(&s.ID, &s.Slug, &s.Status))
			out = append(out, s)
		}
		require.NoError(t, rows.Err())
		return out
	}
	before := readPosts()
	require.Len(t, before, 4)

	t.Run("up leaves existing rows and visibility untouched", func(t *testing.T) {
		tdb.ApplyScript(t, testhelper.MigrationUp)

		assert.Equal(t, before, readPosts())

		var nonLegacy, withLocale int
		require.NoError(t, tdb.SQLDB().QueryRowContext(ctx,
			`SELECT COUNT(*) FILTER (WHERE route_mode <> 'legacy_canonical'),
			        COUNT(*) FILTER (WHERE original_locale IS NOT NULL OR primary_locale IS NOT NULL)
			 FROM posts`).Scan(&nonLegacy, &withLocale))
		assert.Zero(t, nonLegacy)
		assert.Zero(t, withLocale, "legacy original_locale stays NULL until editorially classified")

		assert.Equal(t, 1, countRows(t, tdb, "comments"))
		assert.Equal(t, 1, countRows(t, tdb, "posts_likes"))
		assert.Zero(t, countRows(t, tdb, "post_locales"))
	})

	t.Run("an old-code style insert is still a legacy published post", func(t *testing.T) {
		// Old Symfony/Laravel code knows nothing about the new columns.
		require.NoError(t, execSQL(tdb,
			`INSERT INTO posts (title, slug, content, status, is_featured, user_id) VALUES ('Old code', 'old-code', 'b', FALSE, FALSE, 1)`))

		var mode string
		require.NoError(t, tdb.SQLDB().QueryRowContext(ctx, `SELECT route_mode FROM posts WHERE slug = 'old-code'`).Scan(&mode))
		assert.Equal(t, "legacy_canonical", mode)
	})

	t.Run("the Go post model matches the migrated production-shaped table", func(t *testing.T) {
		assertModelColumnsExist(t, tdb, "posts", reflect.TypeOf(model.Post{}))
	})

	t.Run("a neutral anchor cannot be inserted with the default (published) status", func(t *testing.T) {
		err := execSQL(tdb,
			`INSERT INTO posts (title, slug, content, status, is_featured, user_id, route_mode, primary_locale)
			 VALUES ('Modular', 'modular', '', FALSE, FALSE, 1, 'neutral', 'ru')`)
		require.Error(t, err)
		assert.Contains(t, err.Error(), "status = true")

		require.NoError(t, execSQL(tdb,
			`INSERT INTO posts (title, slug, content, status, is_featured, user_id, route_mode, primary_locale)
			 VALUES ('Modular', 'modular', '', TRUE, FALSE, 1, 'neutral', 'ru')`))
	})

	t.Run("down hides modular anchors, drops the new objects and keeps legacy data", func(t *testing.T) {
		var anchorID int
		require.NoError(t, tdb.SQLDB().QueryRowContext(ctx, `SELECT id FROM posts WHERE slug = 'modular'`).Scan(&anchorID))
		insertLocale(t, tdb, anchorID, "ru", "T", "S", "text", articleDoc("ru"))
		setLocalePublished(t, tdb, anchorID, "ru", true)
		require.NoError(t, execSQL(tdb, `UPDATE posts SET status = FALSE WHERE id = $1`, anchorID))

		tdb.ApplyScript(t, testhelper.MigrationDown)

		var status bool
		require.NoError(t, tdb.SQLDB().QueryRowContext(ctx, `SELECT status FROM posts WHERE slug = 'modular'`).Scan(&status))
		assert.True(t, status, "the anchor row survives a rollback as a hidden draft")

		var objects int
		require.NoError(t, tdb.SQLDB().QueryRowContext(ctx,
			`SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_name IN ('post_locales'))
			      + (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'posts' AND column_name IN ('route_mode', 'original_locale', 'primary_locale'))
			      + (SELECT COUNT(*) FROM pg_trigger WHERE tgname IN ('trg_posts_neutral_insert_must_be_draft'))
			      + (SELECT COUNT(*) FROM pg_proc WHERE proname IN ('posts_neutral_insert_must_be_draft'))`).Scan(&objects))
		assert.Zero(t, objects)

		assert.Equal(t, 1, countRows(t, tdb, "comments"))
		assert.Equal(t, 1, countRows(t, tdb, "posts_likes"))
	})

	t.Run("up can be re-applied after down", func(t *testing.T) {
		tdb.ApplyScript(t, testhelper.MigrationUp)
		assert.Zero(t, countRows(t, tdb, "post_locales"))
	})
}

func TestMigration_ConstraintsAndTriggers(t *testing.T) {
	tdb := testhelper.NewTestDB(t)
	truncateAll(t, tdb)

	ctx := context.Background()
	userID := insertUser(t, tdb, "constraints@test.com")
	postA := insertAnchor(t, tdb, "anchor-a", "ru", userID, nil)
	postB := insertAnchor(t, tdb, "anchor-b", "ru", userID, nil)
	insertLocale(t, tdb, postA, "ru", "A", "S", "t", articleDoc("ru"))

	insertAnchorRow := func(slug string, status bool, primary any) error {
		return execSQL(tdb,
			`INSERT INTO posts (title, slug, content, status, is_featured, views_count, user_id, route_mode, primary_locale)
			 VALUES ('x', $1, '', $2, FALSE, 0, $3, 'neutral', $4)`, slug, status, userID, primary)
	}

	cases := []struct {
		name string
		run  func() error
		want string
	}{
		{"neutral anchor with status=false is rejected", func() error { return insertAnchorRow("s1", false, "ru") }, "status = true"},
		{"neutral anchor without primary locale", func() error { return insertAnchorRow("s2", true, nil) }, ""},
		{"neutral anchor with unknown primary locale", func() error { return insertAnchorRow("s3", true, "fr") }, ""},
		{"unknown route mode", func() error {
			return execSQL(tdb, `UPDATE posts SET route_mode = 'both' WHERE id = $1`, postA)
		}, ""},
		{"unknown original locale", func() error {
			return execSQL(tdb, `UPDATE posts SET original_locale = 'de' WHERE id = $1`, postA)
		}, ""},
		{"duplicate neutral slug", func() error { return insertAnchorRow("anchor-a", true, "ru") }, ""},
		{"unknown locale on post_locales", func() error {
			return execSQL(tdb,
				`INSERT INTO post_locales (post_id, locale, schema_version, title, summary, document)
				 VALUES ($1, 'fr', 1, 't', 's', '{"locale":"fr"}'::jsonb)`, postA)
		}, ""},
		{"locale must match the document locale", func() error {
			return execSQL(tdb,
				`INSERT INTO post_locales (post_id, locale, schema_version, title, summary, document)
				 VALUES ($1, 'en', 1, 't', 's', $2::jsonb)`, postB, articleDoc("ru"))
		}, ""},
		{"document must be an object", func() error {
			return execSQL(tdb,
				`INSERT INTO post_locales (post_id, locale, schema_version, title, summary, document)
				 VALUES ($1, 'ru', 1, 't', 's', '[]'::jsonb)`, postB)
		}, ""},
		{"duplicate locale for one post", func() error {
			return execSQL(tdb,
				`INSERT INTO post_locales (post_id, locale, schema_version, title, summary, document)
				 VALUES ($1, 'ru', 1, 't', 's', $2::jsonb)`, postA, articleDoc("ru"))
		}, ""},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			err := tc.run()
			require.Error(t, err)
			if tc.want != "" {
				assert.Contains(t, err.Error(), tc.want)
			}
		})
	}

	t.Run("deleting a post cascades to its locales", func(t *testing.T) {
		setLocalePublished(t, tdb, postA, "ru", true)
		require.Equal(t, 1, countRows(t, tdb, "post_locales"))

		require.NoError(t, execSQL(tdb, `DELETE FROM posts WHERE id = $1`, postA))

		assert.Zero(t, countRows(t, tdb, "post_locales"))
	})

	t.Run("an unpublished-then-published lifecycle is accepted", func(t *testing.T) {
		insertLocale(t, tdb, postB, "ru", "B", "S", "t", articleDoc("ru"))
		setLocalePublished(t, tdb, postB, "ru", false)
		setLocalePublished(t, tdb, postB, "ru", true)
		setLocalePublished(t, tdb, postB, "ru", false)

		var published sql.NullTime
		require.NoError(t, tdb.SQLDB().QueryRowContext(ctx,
			`SELECT published_at FROM post_locales WHERE post_id = $1 AND locale = 'ru'`, postB).Scan(&published))
		assert.False(t, published.Valid)
	})
}

// TestMigration_GoModelColumnsExistInTestSchema fails when a bun column of model.Post is missing
// from the schema the repository tests run on (and therefore from production after the migration).
func TestMigration_GoModelColumnsExistInTestSchema(t *testing.T) {
	tdb := testhelper.NewTestDB(t)

	assertModelColumnsExist(t, tdb, "posts", reflect.TypeOf(model.Post{}))
}

func assertModelColumnsExist(t *testing.T, tdb *testhelper.TestDB, table string, modelType reflect.Type) {
	t.Helper()

	rows, err := tdb.SQLDB().QueryContext(context.Background(),
		`SELECT column_name FROM information_schema.columns WHERE table_name = $1`, table)
	require.NoError(t, err)
	defer rows.Close()

	have := map[string]bool{}
	for rows.Next() {
		var name string
		require.NoError(t, rows.Scan(&name))
		have[name] = true
	}
	require.NoError(t, rows.Err())

	for _, field := range tdb.DB.Table(modelType).Fields {
		assert.True(t, have[field.Name], "model %s expects column %s.%s", modelType.Name(), table, field.Name)
	}
}
