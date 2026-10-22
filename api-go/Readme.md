# api-go

Go JSON API service running alongside the blog on the same domain at `/api/*`.
Routed via Traefik: requests to `/api/` go to this service, everything else goes to Symfony.
Shares the same PostgreSQL database (read-only for most tables, writes to `comments`, `posts_likes`, and `posts.views_count`).

## Stack

- **Routing**: chi v5
- **Database**: PostgreSQL 17 + pgx v5 + bun ORM
- **Logging**: log/slog (text in dev, JSON in prod)
- **Config**: viper (YAML + env vars)
- **Tests**: testify + testcontainers-go (real PostgreSQL per test)

## Endpoints

Root-level endpoints (no auth):

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sitemap.xml` | Serves `public/seo/sitemap-{MAIN_HOST}.xml`. |
| GET | `/robots.txt` | Serves `public/seo/robots-{MAIN_HOST}.txt`. |
| GET | `/llms.txt` | Serves `public/seo/llms-{MAIN_HOST}.txt`. |
| GET | `/storage/*` | Static file server for uploaded media (`public/storage/`). |

API endpoints — all require `X-API-Key` header except `/api/health`:

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/health` | Health check. No auth required. |
| GET | `/api/` | Paginated posts (10/page), categories with post counts, featured (3) and recent (4) posts. Query: `?page=N` |
| GET | `/api/post/{slug}` | Single post with approved comments, tags, category, author, related posts, random aphorism. Increments `views_count` once per 24h via cookie. |
| GET | `/api/search?q=` | Full-text search across title, description, content (ILIKE). Max 20 results. |
| GET | `/api/tag/{slug}` | Posts by tag, paginated 10/page. |
| GET | `/api/category/{slug}` | Posts by category, paginated 10/page. |
| GET | `/api/contacts` | Static contact info. |
| POST | `/api/contacts` | Submit contact form. JSON: `{name, email?, phone?, message, countMe, honeypot}`. Saves to `incomings` (status=0). Anti-bot: `countMe >= 3`, `honeypot` must be empty. |
| POST | `/api/comment` | Create a comment (status=0, pending moderation). JSON body: `{message, post_id, countMe, honeypot}`. Anti-bot: `countMe >= 3`, `honeypot` must be empty. |
| POST | `/api/postlike/{postId}` | Toggle like on a post. Cookie `likedPostToday{id}` tracks state. First call: creates like, sets cookie. Second call: removes like by exact timestamp, clears cookie. |
| POST | `/api/subscribe` | Newsletter subscription. JSON: `{email}`. Saves to `subscriptions` with UUID token. Idempotent: duplicate email returns 200. |

## Configuration

Via `config.yaml` or environment variables:

| Env var | Description |
|---------|-------------|
| `DATABASE_URL` | PostgreSQL DSN |
| `API_KEY` | Secret key for `X-API-Key` header |
| `APP_ENV` | `dev` or `prod` |
| `SEO_DIR` | Path to SEO files directory (default: `/seo`) |
| `STORAGE_DIR` | Path to storage files directory (default: `/storage`) |

## Running

```bash
# Start with the rest of the stack
docker compose up --build api-go

# Enter container
docker compose exec api-go /bin/sh

# Run tests (requires Docker for testcontainers)
go test ./internal/repository/... -v -timeout 180s
```

## Project structure

```
cmd/api/main.go               # Entry point, DI, graceful shutdown
internal/
  config/config.go            # Viper config
  model/                      # Bun models: Post, Comment, Category, Tag, PostLike, User, Aphorism, Incoming, Subscription
  repository/                 # DB interfaces + implementations
    testhelper/db.go          # Testcontainers helper (PostgreSQL)
    *_test.go                 # Integration tests
  handler/                    # HTTP handlers + response helpers
  middleware/                 # APIKeyAuth, Logging (slog), Recovery
  server/server.go            # Chi router wiring
```

## Notes

- `Post.status = false` means published, `true` means draft. API only serves published posts.
- `Comment.status = 0` means pending, `1` means approved. API only returns approved comments.
- Images: stored as filename in DB, served as `/storage/uploads/{filename}`. Fallback: `/storage/blog_images/no-image.png`.
- Cookie path is `/` so Symfony frontend shares the same view/like cookies.
- Traefik router priority is set to `100` to ensure `/api/` routes are matched before the Symfony catch-all.
