# Geo-IP article-language routing — deferred

**Status:** preserved for a separate follow-up, outside the current modular-articles epic. It is off by default and has not been enabled in production.

## Preserved implementation

- `frontend-astro/src/lib/geoip-country.ts`: local MMDB reader with a bounded retry and unknown-country fallback.
- `frontend-astro/src/lib/geoip-config.ts`: the opt-in flag. Routing is enabled only when `GEOIP_LOCALE_ROUTING_ENABLED=true`.
- `frontend-astro/src/lib/language-choice.ts`: manual cookie parsing, trusted-header parsing and published-locale selection.
- `frontend-astro/src/pages/post/[slug].astro`: the neutral-URL integration; manual published choice wins, and Geo-IP is skipped while the flag is off.
- `frontend-astro/scripts/update-geoip.mjs`: DB-IP Lite release parsing, decompressed-MMDB SHA1 verification, format checks, atomic install and `--once` mode.
- `compose.override.prod.yaml`: `geoip_data` read-only mount for Astro; the updater is under the opt-in Compose profile `geoip`.
- `frontend-astro/src/pages/privacy.astro` and `src/layouts/Layout.astro`: conditional disclosure and DB-IP attribution.

The default Compose value is `false`; the updater profile is not started by a normal Compose `up`. The source, dependency, updater, volume declaration and disclosure text are retained for later use. The local database is not part of this repository.

## Evidence already collected

The isolated DB-IP Lite `2026-11` MMDB was installed with SHA1 `09eea8433fe3eed71984fd6aac9d8ab3d9ca51f0` (8,515,063 bytes). The application's `maxmind` reader opened it and returned country records for representative IPv4 and IPv6 addresses. With the opt-in gate in place, the Astro suite passes (28 tests), and `astro check`/SSR build report 0 errors, 0 warnings and 5 existing hints.

## Required before enabling later

1. Run the updater in an isolated volume and read back release, SHA1 and file size; verify the MMDB with `maxmind`.
2. Verify the exact serving Traefik image/config in an isolated network, without mounting the Docker socket or publishing a public port. Spoof `X-Forwarded-For`, `X-Real-IP`, `True-Client-IP` and `Forwarded`; check IPv4 and IPv6 separately. The required proxy boundary is **not yet verified**.
3. Keep Geo-IP off if the proxy does not demonstrably overwrite the one header the app consumes.
4. Exercise neutral-route precedence, unknown-country fallback, single-locale articles, `Cache-Control: private, no-store`, cookie precedence, sitemap/canonical behavior and the privacy disclosure.
5. Enable the Compose profile and set `GEOIP_LOCALE_ROUTING_ENABLED=true` only after an explicit release decision. Production has not been changed.
