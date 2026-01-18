<div align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
</div>

<div align="center">

[![Laravel](https://img.shields.io/badge/Laravel-%5E12.0-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://docker.com)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)
[![CI](https://img.shields.io/github/actions/workflow/status/dionisvl/my.laravel.blog/ci.yml?style=for-the-badge&logo=github&logoColor=white&label=CI)](https://github.com/dionisvl/my.laravel.blog/actions)

</div>

## 🚀 Laravel Blog Template

- Laravel blog with AdminLTE interface
- TinyMCE editor for rich text editing
- Article tags and categories management
- User registration and admin panel
- Docker-based development environment

## How to Install

### Prerequisites

- Docker and Docker Compose
- Make (optional, for convenience)

### Setup

1. Clone the repository
2. Copy environment file: `cp .env.example .env`
3. Start containers: `make up` or `docker compose up --build`
4. Install dependencies: `make composer-install`
5. Run migrations: `make migrate`
6. **Seed demo data**: `docker compose exec php-fpm php artisan db:seed`
7. Install and build frontend assets:
   ```bash
   make npm-i
   make npx-mix
   ```
8. Configure mail settings in `.env` for subscription functionality

### Quick Start

```bash
git clone [repository-url]
cd phpqa.ru
cp .env.example .env
make init  # Runs full setup
```

## Demo Data & Initialization

### Default Login Credentials

After seeding the database, you can login with:

- **Admin**: `admin@phpqa.ru` / `admin123`
- **User**: `user@phpqa.ru` / `user123`

### Demo Data

The seeder creates:

- 5 categories and 10 tags
- 25 blog posts (20 regular + 5 featured)
- Admin and regular user accounts

### Custom Demo Data

For more detailed information about factories and seeders, see `Docs/FACTORIES.md`

```bash
# Fresh installation with demo data
docker compose exec php-fpm php artisan migrate:fresh --seed

# Add more demo data
docker compose exec php-fpm php artisan db:seed --class=BlogSeeder
```

### Docker Commands

```bash
make up              # Start all containers
make down            # Stop containers
make bash            # Enter PHP container
make build           # Build and start in background
make migrate         # Run database migrations
```

### Deployment

```bash
git pull
make build
make migrate
make npm-run-prod
```

## Testing

Run tests inside the PHP container:

```bash
make bash
./vendor/bin/phpunit --filter TestName
```

Or directly:

```bash
docker compose exec php-fpm ./vendor/bin/phpunit
```

## Telescope

The "Laravel Telescope" will be enabled when `TELESCOPE_ENABLED` is true.
Access will be if `APP_ENV` is local.

##### Installing

```bash
docker compose exec php-fpm php artisan telescope:install
docker compose exec php-fpm php artisan migrate
```

after updating:

```bash
docker compose exec php-fpm php artisan telescope:publish
```

## Debugbar

The Debugbar will be enabled when APP_DEBUG is true.

## Development

### Artisan Commands

Run artisan commands inside the container:

```bash
make bash
php artisan make:model Post -a
php artisan migrate:refresh
php artisan migrate:refresh --path=/database/migrations/fileName.php
```

### Project Structure

- Main Laravel app: `app-laravel/api-laravel/`
- Working directory in container: `/app`
- Access: `http://phpqa.local` (dev) or `https://phpqa.ru` (prod)

### How to test and check honeypot

Open post with comment text field, after it open browser console and write:

```
document.querySelectorAll('input[name="countMe"]')[0].value;

let honeypot = document.getElementById('honeypot');
honeypot.value;
```

### Cache Management

```bash
docker compose exec php-fpm php artisan optimize:clear
docker compose exec php-fpm composer dump-autoload
```
