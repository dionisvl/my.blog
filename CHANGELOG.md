# Release Notes

## Unreleased

- Turbo pages export via API instead of RSS
- Saving cookies in local storage
- Ajax full-text search

### 2026-01-11

- **Laravel Octane with FrankenPHP worker mode** - 8x performance boost
    - Worker mode with max_requests=1000 for memory leak protection
    - OPcache JIT enabled (128MB buffer)
- Migrated from nginx+php-fpm to FrankenPHP
- Laravel 12.46.0 upgrade
- Service renamed: php-fpm → laravel
- Simplified Docker stack (1 process instead of 3)
- Fixed security vulnerabilities

### 2025-01-06

- Laravel 11 upgrade with Rector modernization
- Replaced Form facade with native HTML forms
- Test isolation using SQLite in-memory database
- PHP 8.3+ modern syntax improvements

### 2021-05-30

- Laravel + Docker (nginx + php + mysql)

## (05.01.2020)

- Add loading='lazy' to all imgs

## v1.0.2 (06.08.2019)

- Email subscription completed
- Completed migration of all articles
  from https://sites.google.com/u/0/s/1nH469ev7zFblBP0E4qdUuND2G3lTOuEp/edit?usp=sites_home

## v1.0.1 (06.04.2019)

- Basic search on Laravel

## v1.0.0 (10.03.2019)

- Migration to Laravel 5.7

## v0.0.7 (01.03.2019)

- Migration from shared hosting to VDS MSK-KVM-SSD Xeon E5-2650 v3

## v0.0.6 (01.02.2019)

- Article to category linking
- Category filtering

## v0.0.5 (30.01.2019)

- Show only article titles on homepage

## v0.0.4 (20.01.2019)

- Yandex mail for domain

## v0.0.3 (22.12.2018)

- HeadHunter website parsing for statistical data - MySQL integration with Chart.js for data visualization - Creating
  CRON job for automatic daily parsing

## v0.0.2 (22.11.2018)

- Autosizing of textarea

## v0.0.1 (29.10.2018)
### Added/Changed/Fixed/Removed

- Article editing in TinyMCE
- Add ability to "pin" articles
- Add "add article" button that will fadeIn the article submission form
