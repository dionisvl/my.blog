package main

import (
	"context"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"api-go/internal/config"
	"api-go/internal/model"
	"api-go/internal/repository"
	"api-go/internal/server"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		slog.Error("failed to load config", slog.String("error", err.Error()))
		os.Exit(1)
	}

	logger := setupLogger(cfg.App.Env)

	db := repository.NewDB(cfg.Database)
	defer repository.CloseDB(db)

	db.RegisterModel((*model.PostTag)(nil))

	ctx := context.Background()
	if err := db.PingContext(ctx); err != nil {
		logger.Error("database connection failed", slog.String("error", err.Error()))
		os.Exit(1)
	}
	logger.Info("database connected")

	postRepo := repository.NewPostRepository(db)
	categoryRepo := repository.NewCategoryRepository(db)
	tagRepo := repository.NewTagRepository(db)
	aphorismRepo := repository.NewAphorismRepository(db)
	commentRepo := repository.NewCommentRepository(db)
	postLikeRepo := repository.NewPostLikeRepository(db)
	incomingRepo := repository.NewIncomingRepository(db)
	subscriptionRepo := repository.NewSubscriptionRepository(db)
	articleRepo := repository.NewArticleRepository(db)

	router := server.NewRouter(logger, cfg, postRepo, categoryRepo, tagRepo, aphorismRepo, commentRepo, postLikeRepo, incomingRepo, subscriptionRepo, articleRepo)

	srv := &http.Server{
		Addr:         fmt.Sprintf(":%d", cfg.Server.Port),
		Handler:      router,
		ReadTimeout:  cfg.Server.ReadTimeout,
		WriteTimeout: cfg.Server.WriteTimeout,
	}

	go func() {
		logger.Info("starting server", slog.Int("port", cfg.Server.Port), slog.String("env", cfg.App.Env))
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Error("server failed", slog.String("error", err.Error()))
			os.Exit(1)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	logger.Info("shutting down server")

	shutdownCtx, cancel := context.WithTimeout(context.Background(), cfg.Server.ShutdownTimeout)
	defer cancel()

	if err := srv.Shutdown(shutdownCtx); err != nil {
		logger.Error("server forced to shutdown", slog.String("error", err.Error()))
		os.Exit(1)
	}

	logger.Info("server stopped")
}

func setupLogger(env string) *slog.Logger {
	var h slog.Handler
	if env == "prod" {
		h = slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo})
	} else {
		h = slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelDebug})
	}
	return slog.New(h)
}