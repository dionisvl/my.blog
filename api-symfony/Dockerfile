FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    pdo_mysql \
    intl \
    gd \
    zip \
    opcache

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY . /app

RUN install-php-extensions \
    apcu \
    xdebug

RUN composer install --no-dev --optimize-autoloader --no-scripts

EXPOSE 80 443

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
