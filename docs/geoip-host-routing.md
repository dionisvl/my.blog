# Geo-IP host routing

Readers from Russia are served on `www.<host>` (direct to the server, no Cloudflare). Everyone else, including unknown countries, is served on `<host>` (behind Cloudflare, with caching). The article language of neutral links (`/post/<slug>`) follows the host: `www` opens Russian, the main host opens English. A language chosen with the header selector always wins.

## Pieces

- `compose.override.prod.yaml`: `geoip_data` volume (read-only for Astro), `GEOIP_LOCALE_ROUTING_ENABLED` (default `false`), the `geoip-updater` service (profile `geoip`).
- `frontend-astro/src/middleware.ts`: on `www`, a reader whose country is not RU (or unknown) gets a 302 to the same path on `PUBLIC_SITE_URL` and the `geo_stay` cookie (`Domain=.<host>`, 6 hours), so nobody is sent back and forth. API, assets, feeds and files are never redirected.
- `frontend-astro/src/lib/geoip-country.ts`: local DB-IP Lite lookup of `X-Real-Ip` (no external request).
- `frontend-astro/src/pages/post/[slug].astro`: neutral article links choose the language by host.
- `frontend-astro/scripts/update-geoip.mjs`: downloads the DB-IP Lite country MMDB, verifies SHA1, installs atomically.
- Traefik no longer redirects `www` to the main host (only the retired `OLD_HOST` is redirected).
- Cloudflare (zone `web3main.pro`): the main host is proxied; a redirect rule sends `ip.src.country = "RU"` to `www` unless the `geo_stay` cookie is set; cache rules cache the home page, categories, tags and static files, and never cache admin, login, API, article pages or requests that carry a language or `geo_stay` cookie. SSL mode is Full (strict).

## Operate

- Update the database once: `docker compose -f compose.yml -f compose.override.prod.yaml --profile geoip run --rm geoip-updater node /opt/update-geoip.mjs --once`.
- Enable: set `GEOIP_LOCALE_ROUTING_ENABLED=true` for the compose project and recreate only `astro-frontend`.
- Disable: set it back to `false` and recreate `astro-frontend`. Without the flag `www` serves everyone and the middleware is inert.
- Roll back Cloudflare: delete the redirect rule and set the main host's DNS record back to DNS only.
