FROM dunglas/frankenphp:php8.4 AS base

RUN install-php-extensions \
    pdo_mysql \
    intl \
    gd \
    zip \
    apcu

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

FROM base AS prod

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
COPY . /app

EXPOSE 80 443
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

FROM base AS dev
RUN install-php-extensions xdebug
WORKDIR /app
