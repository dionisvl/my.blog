# Changelog

## Unreleased

- Turbo pages export via API instead of RSS
- Ajax full-text search

## 2026-11-15

- PostgreSQL 17 → 18 (new volume `postgres18_data`; data is moved with `pg_dump` / `pg_restore`)
- Go 1.27.4, Node 26, pnpm 12 (installed via npm: corepack is not shipped since Node 25)
- All image versions moved to `.env` (`IMAGE_*`, `*_VERSION`); monitoring stack updated (Grafana 13)
- Go tests converted to table-driven, `BuildPagination` and `PageFromQuery` covered
- Traefik prod: Prometheus metrics entrypoint restored

## 2026-02-01

- Jaeger distributed tracing: OTLP from Traefik with internal spans
- Jaeger UI with Basic Auth on production
- Log aggregation: VictoriaLogs + VMUI
- Vector collector: Docker container logs → VictoriaLogs
- Symfony: Monolog configured for centralized logging

## 2026-01-29

- Draft/published posts system in Symfony migration
- Random post selection with proper caching
- OPcache configuration for production optimization
- SEO improvements: canonical, hreflang, og:*, twitter:*, JSON-LD (Organization, WebSite, Article)
- Fixed Doctrine deprecations and code style compliance
- Added Aphorism entity with PHP-CS-Fixer configuration
- PHPStan analysis, Rector refactoring, and PHP-CS-Fixer style fixes

## 2026-01-17

- Symfony 7.4 application running in parallel with Laravel
    - Homepage, post detail, contacts pages
    - Likes system (cookie-based, no IP tracking for privacy)
    - Comments system with anti-bot protection
    - Admin panel (posts CRUD with image upload)
    - Modern Symfony 7.4 features: Attributes, DTOs, Repositories, Managers
    - Symfony: expanded admin (catalog, orders, frontparts, etc.), frontend search + pagination
    - Prod routing: Symfony serves prod, Laravel disabled

## 2026-01-11

- Laravel Octane with FrankenPHP worker mode (`max_requests=1000`, OPcache JIT)
- Migrated from nginx+php-fpm to FrankenPHP
- Laravel 12.46.0 upgrade
- Service renamed: php-fpm → laravel
- Simplified Docker stack (1 process instead of 3)
- Fixed security vulnerabilities

## 2025-01-06

- Laravel 11 upgrade with Rector modernization
- Replaced Form facade with native HTML forms
- Test isolation using SQLite in-memory database
- PHP 8.3+ modern syntax improvements

## 2021-05-30

- Laravel + Docker (nginx + php + mysql)

## 2020-01-05

- `loading="lazy"` on all images

## 2019-08-06 (v1.0.2)

- Email subscription completed
- Completed migration of all articles from Google Sites

## 2019-04-06 (v1.0.1)

- Basic search on Laravel

## 2019-03-10 (v1.0.0)

- Migration to Laravel 5.7

## 2019-03-01 (v0.0.7)

- Migration from shared hosting to VDS MSK-KVM-SSD Xeon E5-2650 v3

## 2019-02-01 (v0.0.6)

- Article to category linking
- Category filtering

## 2019-01-30 (v0.0.5)

- Show only article titles on homepage

## 2019-01-20 (v0.0.4)

- Yandex mail for domain

## 2018-12-22 (v0.0.3)

- HeadHunter vacancy statistics: daily cron parser, MySQL storage, Chart.js charts

## 2018-11-22 (v0.0.2)

- Autosizing of textarea

## 2018-10-29 (v0.0.1)

- Article editing in TinyMCE
- Pinned articles
- "Add article" button revealing the submission form
