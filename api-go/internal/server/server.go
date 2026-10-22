package server

import (
	"log/slog"
	"time"

	"github.com/go-chi/chi/v5"
	"github.com/go-chi/chi/v5/middleware"
	"github.com/go-chi/cors"

	"api-go/internal/config"
	"api-go/internal/handler"
	mw "api-go/internal/middleware"
	"api-go/internal/repository"
)

func NewRouter(
	logger *slog.Logger,
	cfg *config.Config,
	postRepo repository.PostRepository,
	categoryRepo repository.CategoryRepository,
	tagRepo repository.TagRepository,
	aphorismRepo repository.AphorismRepository,
	commentRepo repository.CommentRepository,
	postLikeRepo repository.PostLikeRepository,
	incomingRepo repository.IncomingRepository,
	subscriptionRepo repository.SubscriptionRepository,
) *chi.Mux {
	r := chi.NewRouter()

	r.Use(middleware.RequestID)
	r.Use(middleware.RealIP)
	r.Use(mw.Logging(logger))
	r.Use(mw.Recovery(logger))
	r.Use(middleware.Timeout(30 * time.Second))

	if len(cfg.Server.CORSOrigins) > 0 {
		r.Use(cors.Handler(cors.Options{
			AllowedOrigins:   cfg.Server.CORSOrigins,
			AllowedMethods:   []string{"GET", "POST", "OPTIONS"},
			AllowedHeaders:   []string{"Content-Type", "X-API-Key"},
			AllowCredentials: true,
			MaxAge:           300,
		}))
	}

	healthHandler := handler.NewHealthHandler()
	homeHandler := handler.NewHomeHandler(postRepo, categoryRepo, logger)
	postHandler := handler.NewPostHandler(postRepo, categoryRepo, aphorismRepo, logger)
	searchHandler := handler.NewSearchHandler(postRepo, logger)
	tagHandler := handler.NewTagHandler(postRepo, tagRepo, logger)
	categoryHandler := handler.NewCategoryHandler(postRepo, categoryRepo, logger)
	contactsHandler := handler.NewContactsHandler(cfg.App.MainHost)
	commentHandler := handler.NewCommentHandler(commentRepo, logger)
	postLikeHandler := handler.NewPostLikeHandler(postLikeRepo, logger)
	seoHandler := handler.NewSEOHandler(cfg.Files.SeoDir, cfg.App.MainHost, logger)
	storageHandler := handler.NewStorageHandler(cfg.Files.StorageDir)
	incomingHandler := handler.NewIncomingHandler(incomingRepo, logger)
	subscribeHandler := handler.NewSubscribeHandler(subscriptionRepo, logger)

	r.Get("/sitemap.xml", seoHandler.ServeHTTP)
	r.Get("/robots.txt", seoHandler.ServeHTTP)
	r.Get("/llms.txt", seoHandler.ServeHTTP)
	r.Handle("/storage/*", storageHandler)

	r.Route("/api", func(r chi.Router) {
		r.Get("/health", healthHandler.ServeHTTP)

		r.Group(func(r chi.Router) {
			r.Use(mw.APIKeyAuth(cfg.Auth.APIKey))

			r.Get("/", homeHandler.ServeHTTP)
			r.Get("/post/{slug}", postHandler.ServeHTTP)
			r.Get("/search", searchHandler.ServeHTTP)
			r.Get("/tag/{slug}", tagHandler.ServeHTTP)
			r.Get("/category/{slug}", categoryHandler.ServeHTTP)
			r.Get("/contacts", contactsHandler.ServeHTTP)
			r.Post("/contacts", incomingHandler.ServeHTTP)
			r.Post("/comment", commentHandler.ServeHTTP)
			r.Post("/postlike/{postId}", postLikeHandler.ServeHTTP)
			r.Post("/subscribe", subscribeHandler.ServeHTTP)
		})
	})

	return r
}