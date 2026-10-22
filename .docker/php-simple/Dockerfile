ARG PHP_VERSION=8.4.27
FROM php:${PHP_VERSION}-fpm-alpine3.23

RUN apk add --no-cache nginx supervisor \
    gmp-dev \
    postgresql-dev

RUN docker-php-ext-install -j1 \
    gmp pdo_pgsql pgsql

WORKDIR /app

# Configure PHP
COPY conf.d/custom.ini /usr/local/etc/php/conf.d/

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisord.conf

RUN mkdir -p /run/nginx

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
