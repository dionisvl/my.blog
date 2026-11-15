<div align="center">

# Blog Platform — Go API + Astro FE + Symfony Admin

[![Go](https://img.shields.io/badge/Go-1.27-00ADD8?style=for-the-badge&logo=go&logoColor=white)](https://go.dev)
[![Astro](https://img.shields.io/badge/Astro-5-BC52EE?style=for-the-badge&logo=astro&logoColor=white)](https://astro.build)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?style=for-the-badge&logo=symfony&logoColor=white)](https://symfony.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)](https://postgresql.org)
[![Traefik](https://img.shields.io/badge/Traefik-v3-24A1C1?style=for-the-badge&logo=traefikproxy&logoColor=white)](https://traefik.io)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

</div>

A production blog platform running on [web3main.pro](https://web3main.pro). A **Go REST API**
serves all public traffic; Astro renders the public site server-side against that API, and a
Symfony app provides the admin panel. Traefik routes by path, so the three services share one
domain without a gateway of their own.

## Architecture

```
                          ┌──────────────┐
   HTTPS ────────────────▶│   Traefik    │  TLS (Let's Encrypt), HTTP/3
                          └──────┬───────┘  routes by host + path priority
              ┌──────────────────┼──────────────────┐
              ▼                  ▼                  ▼
     /admin, /login      /storage, /sitemap.xml    everything else
      /admin-assets       /robots.txt, /llms.txt         │
              │                  │                       │
       ┌──────▼──────┐    ┌──────▼──────┐        ┌───────▼───────┐
       │   Symfony   │    │   api-go    │◀───────│  Astro (SSR)  │
       │  FrankenPHP │    │  chi + bun  │  REST  │   Node 24     │
       │    admin    │    │             │  X-API │  public site  │
       └──────┬──────┘    └──────┬──────┘  -Key  └───────────────┘
              │                  │
              └────────┬─────────┘
                       ▼
                ┌─────────────┐
                │ PostgreSQL  │
                └─────────────┘
```

Astro never touches the database — it fetches from `api-go` over the internal Docker network,
authenticated with an API key. Public traffic therefore flows through Go, while Symfony is
reachable only on the admin paths.

**Observability:** structured logs are shipped by Vector into VictoriaLogs; Traefik exports
Prometheus metrics and OTLP traces to Jaeger.

## api-go

The main service. Layout follows the [standard Go project layout](https://go.dev/doc/modules/layout).

```
api-go/
├── cmd/api/            entry point — wiring, graceful shutdown
└── internal/
    ├── config/         viper, env-bound, sane defaults
    ├── handler/        HTTP handlers (one file per resource)
    ├── middleware/     API-key auth, structured logging, panic recovery
    ├── model/          bun models
    ├── repository/     data access behind interfaces
    └── server/         chi router and middleware chain
```

**Stack:** chi (routing) · bun + pgdriver (PostgreSQL) · viper (config) ·
`log/slog` (structured logging) · testify + testcontainers-go (tests)

**Design notes**

- Interfaces are declared where they are consumed (`server.NewRouter` accepts
  `repository.PostRepository`), constructors return concrete types — "accept interfaces,
  return structs".
- `context.Context` propagates cancellation; the HTTP server drains in-flight requests on
  SIGINT/SIGTERM within a configurable shutdown timeout.
- Panics in handlers are recovered by middleware, logged with a stack trace, and turned into a
  500 rather than killing the process.
- `slog` emits JSON in production and human-readable text in development.
- Handlers stay thin: request decoding and validation up front, persistence behind a
  repository interface, a single JSON response helper for both success and error shapes.

### Tests

Repository tests spin up a real PostgreSQL container via testcontainers-go — no mocks
standing in for the database. Handler and middleware tests drive the router through
`httptest`, so routing, auth and JSON shaping are covered end to end.

```bash
make test-go        # runs on the host; testcontainers starts its own PostgreSQL
```

## Quick Start

Requires Docker and an `/etc/hosts` entry pointing `blog.test` and `api.blog.test` at `127.0.0.1`.

```bash
cp .env.example .env          # then set MAIN_HOST, API_GO_HOST, GO_API_KEY
make up
```

| Service             | URL                                |
| ------------------- | ---------------------------------- |
| Public site (Astro) | http://blog.test |
| Go API | http://api.blog.test/api/health |
| Admin panel | http://blog.test/admin |
| Traefik dashboard | http://traefik.localhost:8080 |
| Jaeger traces | http://jaeger.localhost |
| Logs (VictoriaLogs) | http://logs.localhost |

## API

All `/api/*` routes except `/api/health` require an `X-API-Key` header.

| Method | Endpoint                  | Purpose                          |
| ------ | ------------------------- | -------------------------------- |
| GET | `/api/health` | Liveness probe (unauthenticated) |
| GET | `/api/` | Homepage feed, paginated |
| GET | `/api/post/{slug}` | Single post |
| GET | `/api/search` | Full-text search |
| GET | `/api/category/{slug}` | Posts by category |
| GET | `/api/tag/{slug}` | Posts by tag |
| POST | `/api/comment` | Submit a comment |
| POST | `/api/postlike/{postId}` | Toggle a like |
| POST | `/api/subscribe` | Newsletter signup |
| POST | `/api/contacts` | Contact form |

`api-go` also serves `/sitemap.xml`, `/robots.txt`, `/llms.txt` (per-domain variants) and
static uploads under `/storage/*`.

## Other Services

**Astro** (`frontend-astro/`) — SSR public frontend on the Node adapter, Tailwind CSS v4,
dark theme. Dev runs Vite with HMR; production builds a standalone server bundle.

**Symfony** (`api-symfony/`) — admin panel on FrankenPHP (Caddy worker mode, zstd/gzip),
Doctrine ORM, AdminLTE. Covers posts, comments, users, categories, tags, products, orders,
portfolio and subscribers. Tests are integration-style on PHPUnit 12.

## Commands

```bash
make up                  # start dev stack
make down                # stop
make build               # rebuild and start
make test-go             # Go tests
make test                # Symfony tests (in container)
make phpstan             # static analysis on changed files
make bash                # shell into the Symfony container
```

See the [Makefile](Makefile) for the full list.

## Deployment

Images are built for `linux/amd64` and pulled from a private registry; Traefik terminates TLS
with Let's Encrypt certificates.

```bash
docker compose -f compose.yml -f compose.override.prod.yaml up -d --build
```

The Symfony image **must** be built from the `prod` stage — `--target prod` — since the
`dev` stage deliberately ships without application code or vendored dependencies.

## Engineering trade-offs

- Public reads go through the Go API, while Symfony remains focused on the admin surface. This keeps the migration incremental instead of requiring a risky full rewrite.
- Astro is isolated from PostgreSQL and receives only the API contract, so frontend rendering and persistence can evolve independently.
- Integration tests use real PostgreSQL containers where persistence semantics matter; lightweight HTTP tests cover routing, middleware and response contracts.
- Deprecated code remains behind explicit boundaries so modernization progress stays reviewable.

## License

MIT — see [LICENSE](LICENSE).
