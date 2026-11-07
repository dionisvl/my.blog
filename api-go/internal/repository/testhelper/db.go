package testhelper

import (
	"context"
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"runtime"
	"strings"
	"testing"

	"github.com/testcontainers/testcontainers-go"
	"github.com/testcontainers/testcontainers-go/wait"
	"github.com/uptrace/bun"
	"github.com/uptrace/bun/dialect/pgdialect"
	"github.com/uptrace/bun/driver/pgdriver"

	"api-go/internal/model"
)

type TestDB struct {
	DB        *bun.DB
	container testcontainers.Container
}

// NewTestDB starts a disposable PostgreSQL 17, creates the legacy tables below and applies the
// modular-articles migration (api-symfony/migrations/sql/modular_articles.up.sql) on top of them.
func NewTestDB(t *testing.T) *TestDB {
	t.Helper()

	tdb := NewBareTestDB(t)
	tdb.migrate(t, context.Background())
	tdb.ApplyScript(t, MigrationUp)

	return tdb
}

// NewBareTestDB starts a disposable PostgreSQL 17 with NO tables, so a test can restore its own
// baseline (e.g. testdata/prod_baseline.sql) and exercise the migration scripts on it.
func NewBareTestDB(t *testing.T) *TestDB {
	t.Helper()
	ctx := context.Background()

	req := testcontainers.ContainerRequest{
		Image:        "postgres:17-alpine",
		ExposedPorts: []string{"5432/tcp"},
		Env: map[string]string{
			"POSTGRES_DB":       "testdb",
			"POSTGRES_USER":     "testuser",
			"POSTGRES_PASSWORD": "testpass",
		},
		WaitingFor: wait.ForLog("database system is ready to accept connections").WithOccurrence(2),
	}

	container, err := testcontainers.GenericContainer(ctx, testcontainers.GenericContainerRequest{
		ContainerRequest: req,
		Started:          true,
	})
	if err != nil {
		t.Fatalf("failed to start postgres container: %v", err)
	}

	host, err := container.Host(ctx)
	if err != nil {
		t.Fatalf("failed to get container host: %v", err)
	}

	port, err := container.MappedPort(ctx, "5432")
	if err != nil {
		t.Fatalf("failed to get container port: %v", err)
	}

	dsn := fmt.Sprintf("postgres://testuser:testpass@%s:%s/testdb?sslmode=disable", host, port.Port())
	sqldb := sql.OpenDB(pgdriver.NewConnector(pgdriver.WithDSN(dsn)))
	db := bun.NewDB(sqldb, pgdialect.New())

	db.RegisterModel((*model.PostTag)(nil))

	tdb := &TestDB{DB: db, container: container}

	t.Cleanup(func() {
		db.Close()
		container.Terminate(ctx)
	})

	return tdb
}

const (
	// MigrationUp and MigrationDown are the Doctrine migration's SQL sources, shared with the Go tests
	// so the test schema cannot drift from the migration.
	MigrationUp   = "modular_articles.up.sql"
	MigrationDown = "modular_articles.down.sql"
)

var statementSeparator = regexp.MustCompile(`(?m)^-- @@[ \t]*$`)

// MigrationStatements reads a script from api-symfony/migrations/sql and splits it on "-- @@" lines.
func MigrationStatements(tb testing.TB, name string) []string {
	tb.Helper()

	_, thisFile, _, ok := runtime.Caller(0)
	if !ok {
		tb.Fatal("cannot locate testhelper source file")
	}

	// .../api-go/internal/repository/testhelper -> repository root is four levels up.
	path := filepath.Join(filepath.Dir(thisFile), "..", "..", "..", "..", "api-symfony", "migrations", "sql", name)
	script, err := os.ReadFile(path)
	if err != nil {
		tb.Fatalf("cannot read migration script %s: %v", path, err)
	}

	return splitStatements(string(script), statementSeparator)
}

// BaselineStatements reads testdata/prod_baseline.sql (statements separated by "-- @@" lines).
func BaselineStatements(tb testing.TB) []string {
	tb.Helper()

	_, thisFile, _, ok := runtime.Caller(0)
	if !ok {
		tb.Fatal("cannot locate testhelper source file")
	}

	path := filepath.Join(filepath.Dir(thisFile), "..", "testdata", "prod_baseline.sql")
	script, err := os.ReadFile(path)
	if err != nil {
		tb.Fatalf("cannot read baseline %s: %v", path, err)
	}

	return splitStatements(string(script), statementSeparator)
}

func splitStatements(script string, separator *regexp.Regexp) []string {
	var statements []string
	for _, chunk := range separator.Split(script, -1) {
		if statement := strings.TrimSpace(chunk); statement != "" {
			statements = append(statements, statement)
		}
	}
	return statements
}

// ApplyScript executes a migration script from api-symfony/migrations/sql in one transaction.
func (t *TestDB) ApplyScript(tb testing.TB, name string) {
	tb.Helper()
	t.ApplyStatements(tb, MigrationStatements(tb, name))
}

// ApplyStatements executes statements in one transaction, like Doctrine does for a migration.
func (t *TestDB) ApplyStatements(tb testing.TB, statements []string) {
	tb.Helper()

	if err := t.TryApplyStatements(statements); err != nil {
		tb.Fatalf("applying statements failed: %v", err)
	}
}

// TryApplyStatements is ApplyStatements returning the error instead of failing the test.
func (t *TestDB) TryApplyStatements(statements []string) error {
	ctx := context.Background()

	tx, err := t.DB.DB.BeginTx(ctx, nil)
	if err != nil {
		return err
	}

	for _, statement := range statements {
		if _, err := tx.ExecContext(ctx, statement); err != nil {
			_ = tx.Rollback()
			return fmt.Errorf("%w\nstatement: %s", err, statement)
		}
	}

	return tx.Commit()
}

func (t *TestDB) migrate(tb testing.TB, ctx context.Context) {
	tb.Helper()

	// Use the underlying sql.DB to avoid bun rewriting DDL placeholders
	sqlDB := t.DB.DB

	queries := []string{
		`CREATE TABLE IF NOT EXISTS users (
			id         SERIAL PRIMARY KEY,
			name       VARCHAR(255) NOT NULL DEFAULT '',
			email      VARCHAR(255) NOT NULL UNIQUE,
			password   VARCHAR(255) NOT NULL DEFAULT '',
			is_admin   BOOLEAN NOT NULL DEFAULT FALSE,
			status     INT NOT NULL DEFAULT 0,
			created_at TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
		`CREATE TABLE IF NOT EXISTS categories (
			id           SERIAL PRIMARY KEY,
			title        VARCHAR(255) NOT NULL,
			slug         VARCHAR(255) NOT NULL UNIQUE,
			preview_text TEXT,
			detail_text  TEXT,
			created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at   TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
		`CREATE TABLE IF NOT EXISTS posts (
			id          SERIAL PRIMARY KEY,
			title       VARCHAR(255) NOT NULL,
			slug        VARCHAR(255) NOT NULL UNIQUE,
			content     TEXT,
			description TEXT,
			image       VARCHAR(255),
			status      BOOLEAN NOT NULL DEFAULT FALSE,
			is_featured BOOLEAN NOT NULL DEFAULT FALSE,
			views_count INT NOT NULL DEFAULT 0,
			user_id     INT NOT NULL REFERENCES users(id),
			category_id INT REFERENCES categories(id),
			created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at  TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
		`CREATE TABLE IF NOT EXISTS tags (
			id         SERIAL PRIMARY KEY,
			title      VARCHAR(255) NOT NULL,
			slug       VARCHAR(255) NOT NULL UNIQUE,
			created_at TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
		`CREATE TABLE IF NOT EXISTS post_tags (
			post_id INT NOT NULL REFERENCES posts(id),
			tag_id  INT NOT NULL REFERENCES tags(id),
			PRIMARY KEY (post_id, tag_id)
		)`,
		`CREATE TABLE IF NOT EXISTS comments (
			id          SERIAL PRIMARY KEY,
			author_name VARCHAR(255) NOT NULL DEFAULT 'anon',
			text        TEXT NOT NULL,
			post_id     INT NOT NULL REFERENCES posts(id),
			user_id     INT REFERENCES users(id),
			status      INT NOT NULL DEFAULT 0,
			created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at  TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
		`CREATE TABLE IF NOT EXISTS posts_likes (
			id            SERIAL PRIMARY KEY,
			post_id       INT NOT NULL REFERENCES posts(id),
			ip            VARCHAR(255),
			device_memory INT,
			created_at    TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at    TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
		`CREATE TABLE IF NOT EXISTS aphorism (
			id          SERIAL PRIMARY KEY,
			detail_text VARCHAR(255) NOT NULL,
			created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at  TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
		`CREATE TABLE IF NOT EXISTS incomings (
			id         SERIAL PRIMARY KEY,
			name       TEXT NOT NULL,
			email      TEXT,
			phone      TEXT,
			message    TEXT NOT NULL,
			status     INT NOT NULL DEFAULT 0,
			created_at TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
		`CREATE TABLE IF NOT EXISTS subscriptions (
			id         SERIAL PRIMARY KEY,
			email      VARCHAR(255) NOT NULL UNIQUE,
			token      VARCHAR(255),
			created_at TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at TIMESTAMP NOT NULL DEFAULT NOW()
		)`,
	}

	for _, q := range queries {
		if _, err := sqlDB.ExecContext(ctx, q); err != nil {
			tb.Fatalf("migration failed: %v\nquery: %s", err, q)
		}
	}
}

func (t *TestDB) Truncate(tb testing.TB, tables ...string) {
	tb.Helper()
	ctx := context.Background()
	sqlDB := t.DB.DB
	for _, table := range tables {
		if _, err := sqlDB.ExecContext(ctx, fmt.Sprintf("TRUNCATE TABLE %s RESTART IDENTITY CASCADE", table)); err != nil {
			tb.Fatalf("truncate %s failed: %v", table, err)
		}
	}
}

// SQLDB returns the underlying *sql.DB for raw queries in tests.
func (t *TestDB) SQLDB() *sql.DB {
	return t.DB.DB
}
