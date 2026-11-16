# frontend-astro

Public site: Astro 7 in SSR mode on the `@astrojs/node` standalone adapter. Pages are rendered
from the Go API (`api-go`); the frontend has no database access.

## Stack

- **Astro 7**, `output: "server"`
- **Tailwind CSS v4** via `@tailwindcss/vite`, dark theme
- **Shiki** for code highlighting
- **pnpm**, **Node 26**

## Running

From the repo root, with the rest of the stack:

```bash
make up                  # dev server with HMR at http://$MAIN_HOST
make astro-build-local   # production build served locally in place of the dev server
```

Standalone, without Docker:

```bash
cp .env.example .env     # API_URL must point at a reachable api-go
pnpm install
pnpm dev
```

## Environment variables

| Variable                          | Description                                               |
| --------------------------------- | --------------------------------------------------------- |
| `API_URL`                         | Go API URL, e.g. `http://api-go:8081`                     |
| `API_KEY`                         | API key; compose sets it from `GO_API_KEY` in root `.env` |
| `PUBLIC_SITE_URL`                 | Canonical site URL; its host is the only one Astro trusts |
| `PUBLIC_SITE_TITLE`               | Site title                                                |
| `PUBLIC_SITE_AUTHOR`              | Site author                                               |
| `PUBLIC_SITE_DESC`                | Meta description                                          |
| `PUBLIC_GOOGLE_SITE_VERIFICATION` | Google Search Console meta tag (optional)                 |
| `GEOIP_LOCALE_ROUTING_ENABLED`    | Geo-IP host routing, off by default (prod only)           |

`API_URL` and `API_KEY` are server-only and never reach the browser. In production the
`PUBLIC_*` values are baked in at build time.

## Scripts

```bash
pnpm dev            # dev server
pnpm build          # type-check + production build
pnpm test           # unit tests (node --test)
pnpm lint           # ESLint
pnpm format         # Prettier (write)
pnpm format:check   # Prettier (check)
pnpm sync           # regenerate Astro types
```

## Project structure

```
src/
├── pages/
│   ├── index.astro                 /            home, paginated with ?page=N
│   ├── post/[slug].astro           /post/<slug>
│   ├── [lang]/post/[slug].astro    /<ru|en>/post/<slug>
│   ├── category/[slug].astro       /category/<slug>
│   ├── tag/[slug].astro            /tag/<slug>
│   ├── search.astro                /search?q=
│   ├── contacts.astro, privacy.astro, 404.astro
│   └── api/                        browser-facing endpoints that proxy to api-go
│       ├── comment.ts
│       ├── postlike/[postId].ts
│       └── language-preference.ts
├── components/article/             renderers for article blocks
├── contracts/article-blocks/v1/    JSON contract shared with Symfony
├── layouts/Layout.astro
├── lib/                            API client, types, i18n, geo-IP, URL helpers
├── middleware.ts                   geo-IP host routing
└── styles/global.css
public/                             static CSS, JS, icons
scripts/update-geoip.mjs            downloads the DB-IP Lite country database
tests/                              unit tests
```

## Notes

- The browser never calls `api-go` directly: writes go through the `src/pages/api/` routes,
  which add the API key server-side.
- Modular articles are stored as typed blocks. The contract in `src/contracts/article-blocks/v1/`
  mirrors `api-symfony/contracts/article-blocks/v1/`.
- Article language and geo-IP host routing are described in
  [Docs/geoip-host-routing.md](../Docs/geoip-host-routing.md).
- Traefik sends everything on `MAIN_HOST` to Astro except the admin, storage and SEO paths.
