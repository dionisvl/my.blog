package repository

import (
	"database/sql"

	"github.com/uptrace/bun"
	"github.com/uptrace/bun/dialect/pgdialect"
	"github.com/uptrace/bun/driver/pgdriver"

	"api-go/internal/config"
)

func NewDB(cfg config.DatabaseConfig) *bun.DB {
	sqldb := sql.OpenDB(pgdriver.NewConnector(pgdriver.WithDSN(cfg.URL)))
	sqldb.SetMaxOpenConns(cfg.MaxOpenConns)
	sqldb.SetMaxIdleConns(cfg.MaxIdleConns)
	sqldb.SetConnMaxLifetime(cfg.ConnMaxLifetime)
	sqldb.SetConnMaxIdleTime(cfg.ConnMaxIdleTime)

	return bun.NewDB(sqldb, pgdialect.New())
}

func CloseDB(db *bun.DB) error {
	return db.Close()
}
