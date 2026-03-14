FROM php:8.3.19-fpm-alpine3.21

RUN apk add --no-cache nginx supervisor \
    php83-gmp gmp \
    postgresql-dev

RUN echo "extension=/usr/lib/php83/modules/gmp.so" > /usr/local/etc/php/conf.d/gmp.ini

RUN docker-php-ext-install -j$(nproc) \
    pdo_pgsql pgsql

WORKDIR /app

# Configure PHP
COPY conf.d/custom.ini /usr/local/etc/php/conf.d/

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisord.conf

RUN mkdir -p /run/nginx

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
