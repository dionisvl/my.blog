# api-go

Go JSON API behind the public site. Astro calls it over the internal Docker network; Traefik
also exposes it on its own host (`API_GO_HOST`) and routes `/storage/*`, `/sitemap.xml`,
`/robots.txt` and `/llms.txt` of the main host to it.

It shares the PostgreSQL database with the Symfony admin and mostly reads. It writes only
comments, likes, contact messages, subscriptions and `posts.views_count`.

## Stack

- **Routing**: chi v5
- **Database**: PostgreSQL 18, bun ORM with `pgdriver`
- **Logging**: `log/slog` (text in dev, JSON in prod)
- **Config**: viper (`config.yaml` + env vars)
- **Tests**: testify + testcontainers-go (real PostgreSQL)

## Endpoints

No auth:

| Method | Path           | Description                                                                         |
| ------ | -------------- | ----------------------------------------------------------------------------------- |
| GET    | `/api/health`  | Health check.                                                                       |
| GET    | `/sitemap.xml` | `sitemap-{MAIN_HOST}.xml` from `SEO_DIR`, extended with published article locales.  |
| GET    | `/robots.txt`  | `robots-{MAIN_HOST}.txt` from `SEO_DIR`.                                            |
| GET    | `/llms.txt`    | `llms-{MAIN_HOST}.txt` from `SEO_DIR`.                                              |
| GET    | `/storage/*`   | Uploaded media from `STORAGE_DIR`, no directory listing.                            |

`X-API-Key` header required:

| Method | Path                               | Description                                                                                                  |
| ------ | ---------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| GET    | `/api/`                            | Posts (10 per page, `?page=N`), categories, 3 featured and 4 recent posts.                                   |
| GET    | `/api/post/{slug}`                 | Post with approved comments, tags, category, author, related posts and a random aphorism. Counts one view per 24 h (cookie `viewedPostToday{id}`). |
| GET    | `/api/post/{slug}/resolve`         | Routing data of a modular article: its published locales.                                                    |
| GET    | `/api/post/{slug}/locale/{locale}` | Content of one published locale (`ru` or `en`).                                                              |
| GET    | `/api/search?q=`                   | Case-insensitive match in title, description and content. Up to 20 results.                                  |
| GET    | `/api/tag/{slug}`                  | Posts by tag, 10 per page.                                                                                   |
| GET    | `/api/category/{slug}`             | Posts by category, 10 per page.                                                                              |
| GET    | `/api/contacts`                    | Static contact info.                                                                                         |
| POST   | `/api/contacts`                    | Contact form: `{name, email?, phone?, message, countMe, honeypot}`.                                          |
| POST   | `/api/comment`                     | New comment, pending moderation: `{message, post_id, countMe, honeypot}`.                                    |
| POST   | `/api/postlike/{postId}`           | Toggles a like. Cookie `likedPostToday{id}` holds the like ID; a second call removes that like.              |
| POST   | `/api/subscribe`                   | `{email}`. Returns 201, or 200 if the email is already subscribed.                                           |

List endpoints accept `?lang=ru|en` (default `en`) to pick the locale of article cards.
Anti-bot check on the POST forms: `countMe >= 3` and an empty `honeypot`.

## Configuration

`config.yaml` holds the defaults; environment variables override them.

| Env var                | Description                                                  |
| ---------------------- | ------------------------------------------------------------ |
| `DATABASE_URL`         | PostgreSQL DSN                                               |
| `API_KEY`              | Secret for the `X-API-Key` header (required)                 |
| `MAIN_HOST`            | Public host; selects the SEO files (required)                |
| `APP_ENV`              | `dev` or `prod`                                              |
| `CORS_ALLOWED_ORIGINS` | Comma-separated origins; CORS is off when empty              |
| `TRUSTED_PROXIES`      | Comma-separated CIDRs trusted for `X-Forwarded-For` (default: loopback and private ranges) |
| `SEO_DIR`              | Directory with SEO files (default `/seo`)                    |
| `STORAGE_DIR`          | Directory with uploads (default `/storage`)                  |
| `MEDIA_BASE_URL`       | Public prefix of media URLs (default `/storage`)             |

## Running

```bash
make up                      # from the repo root: starts api-go with the rest of the stack
make test-go                 # from the repo root: all Go tests; needs Docker for testcontainers
go test ./internal/handler/  # a single package
```

## Project structure

```
cmd/api/main.go     entry point, wiring, graceful shutdown
internal/
  config/           viper config
  model/            bun models
  repository/       interfaces + implementations, integration tests
    testhelper/     testcontainers PostgreSQL helper
  handler/          HTTP handlers and response helpers
  middleware/       API key auth, slog request logging, panic recovery
  server/           chi router
```

## Notes

- `posts.status = false` means published, `true` means draft. Only published posts are served.
- `comments.status = 0` means pending, `1` means approved. Only approved comments are returned.
- A modular article is public once at least one of its locales is published.
- `posts.image` holds a file name, served as `{MEDIA_BASE_URL}/uploads/{filename}`;
  the fallback is `{MEDIA_BASE_URL}/blog_images/no-image.png`.
