# FrankenPHP Migration Guide

## ✅ Migration Completed!

Laravel blog successfully migrated from **nginx + php-fpm** to **FrankenPHP** (Caddy + PHP in one container).

## 📦 What Changed

### Docker Stack

- **Before**: nginx + php-fpm + supervisor (3 processes)
- **After**: FrankenPHP (1 process, Caddy + PHP embedded)

### Service Name

- **Before**: `php-fpm`
- **After**: `frankenphp`

### Configuration Files

- **Removed**: nginx config, supervisor config
- **Added**: `Caddyfile` (simpler than nginx)

## 🗂️ File Structure

### New Files

```
app-laravel/
├── Dockerfile              # FrankenPHP-based (was Dockerfile.frankenphp)
├── Caddyfile              # Caddy server config for Laravel routing
└── php/
    └── conf.d/
        ├── custom.ini     # PHP settings
        └── 50_xdebug.ini  # Xdebug config (dev only)
```

### Deprecated Files (moved to `deprecated/`)

```
deprecated/
├── compose.override.yaml     # Old override file
├── compose.override.dev.yaml # Old dev override
├── compose.override.prod.yaml # Old prod override
├── Dockerfile.old           # Old nginx+php-fpm Dockerfile
├── nginx/                   # Nginx configs
├── php/                     # Old PHP configs
└── supervisor/              # Supervisor configs
```

### Updated Files

```
compose.yml                    # Service renamed: php-fpm → frankenphp
compose.override.dev.yaml      # New dev config for FrankenPHP
compose.override.prod.yaml     # New prod config for FrankenPHP
Makefile                       # All commands updated: php-fpm → frankenphp
```

## 🚀 Usage

### Development (Local)

```bash
# Start services
make up
# or
docker compose -f compose.yml -f compose.override.dev.yaml up -d

# Access shell
make bash

# Run artisan
docker compose exec frankenphp php artisan migrate

# Run tests
make test
```

### Production

```bash
# Start services
docker compose -f compose.yml -f compose.override.prod.yaml up -d

# Build and start
docker compose -f compose.yml -f compose.override.prod.yaml up --build -d
```

## 🔧 Makefile Commands

All commands work as before, just with `frankenphp` container:

```bash
make up              # Start containers
make down            # Stop containers
make build           # Build and start
make bash            # Enter PHP container shell
make migrate         # Run migrations
make test            # Run PHPUnit tests
make rector          # Run Rector (dry-run)
make phpstan         # Run PHPStan
make cs-fix          # Run PHP-CS-Fixer
```

## 📊 Technical Details

### PHP Version

- PHP 8.4.16 (ZTS - Thread Safe)
- Laravel 12.19.3

### Installed Extensions

- **Database**: mysqli, pdo_mysql, pdo_pgsql, pgsql
- **Image**: gd (with freetype, jpeg)
- **Compression**: zip
- **Math**: bcmath
- **Caching**: opcache, redis
- **Other**: exif, calendar, intl, gettext, pcntl, sockets, sysvmsg, sysvsem, sysvshm
- **PECL**: redis, amqp (RabbitMQ)
- **Dev only**: xdebug

### Exposed Ports

- `80` - HTTP
- `443` - HTTPS (through Traefik)
- `443/udp` - HTTP/3 (through Traefik)

### Volumes

```yaml
volumes:
  - ./app-laravel/api-laravel:/app:rw
  - ./app-laravel/storage/public:/app/public/storage:rw
  - ./app-laravel/Caddyfile:/etc/caddy/Caddyfile:ro
```

## 🌐 Domains

### Development

- Main app: `http://phpqa.local`
- Traefik dashboard: `http://traefik.localhost`

### Production

- Main app: `https://phpqa.ru`, `https://web3main.pro`
- Traefik dashboard: `https://traefik.phpqa.ru`, `https://traefik.web3main.pro`

## ⚡ Performance

FrankenPHP advantages:

- **Worker mode**: Keeps PHP app in memory (like Octane, but simpler)
- **HTTP/2 & HTTP/3**: Native support through Caddy
- **Zero config**: Works out-of-the-box for Laravel
- **Lower memory**: Single process vs 3 processes (nginx + php-fpm + supervisor)

## 🛠️ Customization

### Caddyfile

Edit `app-laravel/Caddyfile` for server configuration:

- Change root directory
- Add custom headers
- Enable/disable compression
- Configure logging

### PHP Settings

Edit `app-laravel/php/conf.d/custom.ini`:

```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 600
opcache.enable = 1
```

### Disable Xdebug (Production)

In `app-laravel/Dockerfile`, comment out xdebug:

```dockerfile
pecl install \
    # xdebug \  # Comment this line
    redis \
    amqp
```

## 🔍 Troubleshooting

### Check logs

```bash
docker logs phpqaru-frankenphp-1
docker logs phpqaru-frankenphp-1 --tail 50 --follow
```

### Restart container

```bash
docker compose restart frankenphp
```

### Rebuild from scratch

```bash
docker compose down
docker compose build --no-cache frankenphp
docker compose up -d
```

### Check PHP extensions

```bash
docker compose exec frankenphp php -m
```

### Check Laravel status

```bash
docker compose exec frankenphp php artisan --version
docker compose exec frankenphp php artisan migrate:status
```

## 📝 Rollback (if needed)

If something goes wrong, you can rollback:

```bash
# Stop current stack
docker compose down

# Restore old files
mv deprecated/Dockerfile.old app-laravel/Dockerfile
mv deprecated/compose.override.dev.yaml compose.override.dev.yaml
mv deprecated/compose.override.prod.yaml compose.override.prod.yaml
mv deprecated/nginx app-laravel/
mv deprecated/php app-laravel/
mv deprecated/supervisor app-laravel/

# Restore service name in compose.yml
# Change: frankenphp → php-fpm

# Restore Makefile
# Change: frankenphp → php-fpm

# Rebuild and start
docker compose build
docker compose up -d
```

## 🎯 Next Steps

1. **Test thoroughly** in development
2. **Deploy to staging** first
3. **Monitor performance** and error logs
4. **Optimize OPcache** settings if needed
5. **Consider enabling** FrankenPHP worker mode for even better performance (advanced)

## 📚 Resources

- FrankenPHP Documentation: https://frankenphp.dev/
- Caddyfile Syntax: https://caddyserver.com/docs/caddyfile
- Laravel + FrankenPHP: https://frankenphp.dev/docs/laravel/

---

**Migration Date**: 2026-01-11
**PHP Version**: 8.4.16
**Laravel Version**: 12.19.3
**FrankenPHP Version**: 1-php8.4-alpine
