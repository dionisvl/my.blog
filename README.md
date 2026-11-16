<div align="center">

# Blog Platform — Go API + Astro Frontend + Symfony Admin

[![CI](https://github.com/dionisvl/my.blog/actions/workflows/ci.yml/badge.svg)](https://github.com/dionisvl/my.blog/actions/workflows/ci.yml)
[![Go](https://img.shields.io/badge/Go-1.27-00ADD8?logo=go&logoColor=white)](https://go.dev)
[![Astro](https://img.shields.io/badge/Astro-7-BC52EE?logo=astro&logoColor=white)](https://astro.build)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white)](https://postgresql.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

</div>

The blog platform behind [web3main.pro](https://web3main.pro). Astro renders the public site
server-side from a **Go REST API**, a Symfony app provides the admin panel, and Traefik routes
between them by path, so all three share one domain.

## Architecture

```
                          ┌──────────────┐
   HTTPS ────────────────▶│   Traefik    │  TLS (Let's Encrypt), HTTP/3
                          └──────┬───────┘  routes by host + path priority
              ┌──────────────────┼──────────────────┐
              ▼                  ▼                  ▼
     /admin, /login      /storage, /sitemap.xml    everything else
     /logout             /robots.txt, /llms.txt          │
     /admin-assets               │                       │
       ┌──────▼──────┐    ┌──────▼──────┐        ┌───────▼───────┐
       │   Symfony   │    │   api-go    │◀───────│  Astro (SSR)  │
       │  FrankenPHP │    │  chi + bun  │  REST  │   Node 26     │
       │    admin    │    │             │  X-API │  public site  │
       └──────┬──────┘    └──────┬──────┘  -Key  └───────────────┘
              │                  │
              └────────┬─────────┘
                       ▼
                ┌─────────────┐
                │ PostgreSQL  │
                └─────────────┘
```

Astro never touches the database: it calls `api-go` over the internal Docker network with an
API key, so the browser never sees the key. Symfony is reachable only on the admin paths.
`api-go` is also exposed directly on its own host (`API_GO_HOST`).

**Observability:** Vector ships container logs to VictoriaLogs; Traefik sends OTLP traces to
Jaeger and exposes Prometheus metrics for the optional Grafana stack. See [Docs](Docs/Readme.md).

## Services

| Directory         | Role                                                                                     | Details                              |
| ----------------- | ---------------------------------------------------------------------------------------- | ------------------------------------ |
| `api-go/`         | Public REST API, static uploads, sitemap / robots / llms.txt                             | [Readme](api-go/Readme.md)           |
| `frontend-astro/` | SSR public site: Astro 7, Node adapter, Tailwind CSS v4                                  | [Readme](frontend-astro/Readme.md)   |
| `api-symfony/`    | Admin panel: Symfony 7.4 on FrankenPHP (worker mode), Doctrine ORM, AdminLTE             | —                                    |

The admin covers posts, modular multilingual articles, comments, users, categories, tags,
products, orders, portfolio, subscribers and contact messages. Articles are stored as typed
blocks validated against a shared [JSON contract](api-symfony/contracts/article-blocks/v1/CONTRACT.md).

### api-go design notes

- Layout follows the [standard Go project layout](https://go.dev/doc/modules/layout):
  `cmd/api` for wiring, `internal/{config,handler,middleware,model,repository,server}`.
- Handlers depend on repository interfaces; `server.NewRouter` receives them as arguments.
- The server drains in-flight requests on SIGINT/SIGTERM within a configurable timeout.
- Panics are recovered by middleware, logged, and turned into a 500.
- `log/slog` emits JSON in production and text in development.

## Quick Start

Requires Docker and an `/etc/hosts` entry pointing `blog.test` and `api.blog.test` at `127.0.0.1`.

```bash
cp .env.example .env
cp api-go/.env.example api-go/.env
cp frontend-astro/.env.example frontend-astro/.env
cp api-symfony/.env.dev.example api-symfony/.env
make up
make composer-install
```

Compose passes `GO_API_KEY` from the root `.env` to both `api-go` and Astro as `API_KEY`.

The database schema predates the Symfony app and is not created by migrations, and there is no
seed data. Restore a dump of an existing database, then run `make migrates`.

| Service             | URL                             |
| ------------------- | ------------------------------- |
| Public site (Astro) | http://blog.test                |
| Go API              | http://api.blog.test/api/health |
| Admin panel         | http://blog.test/admin          |
| Traefik dashboard   | http://localhost:8080           |
| Jaeger traces       | http://jaeger.localhost         |
| Logs (VictoriaLogs) | http://logs.localhost           |

## API

All `/api/*` routes except `/api/health` require an `X-API-Key` header.

| Method | Endpoint                                | Purpose                                 |
| ------ | --------------------------------------- | --------------------------------------- |
| GET    | `/api/health`                           | Liveness probe (unauthenticated)        |
| GET    | `/api/`                                 | Homepage feed, paginated                |
| GET    | `/api/post/{slug}`                      | Single post                             |
| GET    | `/api/post/{slug}/resolve`              | Published locales of a modular article  |
| GET    | `/api/post/{slug}/locale/{locale}`      | One published locale of an article      |
| GET    | `/api/search`                           | Search in titles, descriptions, content |
| GET    | `/api/category/{slug}`                  | Posts by category                       |
| GET    | `/api/tag/{slug}`                       | Posts by tag                            |
| GET    | `/api/contacts`                         | Contact details                         |
| POST   | `/api/contacts`                         | Contact form                            |
| POST   | `/api/comment`                          | Submit a comment                        |
| POST   | `/api/postlike/{postId}`                | Toggle a like                           |
| POST   | `/api/subscribe`                        | Newsletter signup                       |

`api-go` also serves `/sitemap.xml`, `/robots.txt`, `/llms.txt` (per-domain files) and static
uploads under `/storage/*`. Details: [api-go/Readme.md](api-go/Readme.md).

## Commands

```bash
make up                  # start the dev stack
make down                # stop it
make build               # rebuild and start
make test                # Symfony tests (PHPUnit, in the container)
make test-go             # Go tests (on the host; testcontainers starts its own PostgreSQL)
make phpstan             # static analysis of changed PHP files
make cs-fix              # PHP CS Fixer
make bash                # shell into the Symfony container
make monitoring-up       # optional Prometheus + Grafana stack
```

See the [Makefile](Makefile) for the full list.

## Tests and CI

- **Go:** repository tests run against a real PostgreSQL started by testcontainers-go; handlers
  and middleware are tested through `httptest`.
- **Symfony:** PHPUnit 12, integration-style, on SQLite.
- **Astro:** `node --test` unit tests plus `astro check`.
- **Cross-runtime:** CI publishes an article with Symfony and reads it back with Go on the same
  PostgreSQL, so the two services cannot drift apart on the shared schema.

## Deployment

```bash
docker compose -f compose.yml -f compose.override.prod.yaml up -d --build
```

Traefik terminates TLS with Let's Encrypt certificates. The Symfony image must be built from the
`prod` stage (the compose file sets `target: prod`): the `dev` stage ships without application
code or vendored dependencies. Image versions are pinned in `.env`.

## License

MIT — see [LICENSE](LICENSE).
