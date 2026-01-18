<div align="center">
  <img src="https://symfony.com/images/logos/header-logo.svg" width="320" alt="Symfony Logo">
</div>

<div align="center">

[![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?style=for-the-badge&logo=symfony&logoColor=white)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://docker.com)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)
[![CI](https://img.shields.io/github/actions/workflow/status/dionisvl/my.laravel.blog/ci.yml?style=for-the-badge&logo=github&logoColor=white&label=CI)](https://github.com/dionisvl/my.laravel.blog/actions)

</div>

# Symfony Blog/Ecommerce template

Symfony blog/admin app. Laravel remains in the repo as legacy reference and for dev-only usage.

## Key Facts

- **Primary app**: `api-symfony/`
- **Legacy app**: `app-laravel/` (do not use in prod)
- **PHP**: 8.4+
- **Server**: FrankenPHP (Caddy) with worker mode, zstd/gzip compression

## Development

### Start (dev)

```bash
make up-dev
```

### Stop

```bash
make down
```

## Production

Symfony serves prod domains; Laravel is disabled.

```bash
make up-prod
```

## Tests

```bash
make test-symfony
```

- Symfony tests run on SQLite (`api-symfony/.env.test`).

## Useful Commands

```bash
see Makefile for all commands
```

## Project Notes

- Admin panel uses AdminLTE.
- Search, categories/tags filters, and pagination are implemented.
- Health endpoint: `/health`.
- OPcache production settings mounted from `api-symfony/php/conf.d/opcache.prod.ini`.

## Repo Layout

- `api-symfony/` Symfony app (current)
- `app-laravel/` Laravel legacy app (reference only)
- `compose.yml` base Docker Compose
- `compose.override.dev.yaml` dev overrides
- `compose.override.prod.yaml` prod overrides
