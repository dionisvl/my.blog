FROM dunglas/frankenphp:php8.4 AS prod

RUN install-php-extensions \
    pdo_mysql \
    intl \
    gd \
    zip \
    apcu

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --no-dev --optimize-autoloader --no-scripts

EXPOSE 80 443

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

FROM prod AS dev

RUN install-php-extensions xdebug
