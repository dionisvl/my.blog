FROM dunglas/frankenphp:php8.4 AS prod

# force ipv4 for apt to avoid issues in some networks
RUN printf 'Acquire::ForceIPv4 "true";\nAcquire::Retries "5";\nAcquire::http::Timeout "120";\n' > /etc/apt/apt.conf.d/99force-ipv4

RUN apt-get update

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
