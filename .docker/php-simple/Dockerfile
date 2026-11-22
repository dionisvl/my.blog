ARG PHP_VERSION=8.4.28
FROM php:${PHP_VERSION}-fpm-alpine3.24

RUN apk add --no-cache supervisor curl \
    gmp-dev \
    postgresql-dev

RUN docker-php-ext-install -j2 gmp opcache pdo_pgsql pgsql

RUN curl -fsSL https://nginx.org/keys/nginx_signing.rsa.pub -o /etc/apk/keys/nginx_signing.rsa.pub \
    && echo 'ff7cf138acc09f2a5029300ab713fe6a1440605fca72e2bab76a4da9206fec87  /etc/apk/keys/nginx_signing.rsa.pub' | sha256sum -c - \
    && echo '@nginx https://nginx.org/packages/mainline/alpine/v3.24/main' >> /etc/apk/repositories \
    && apk add --no-cache 'nginx@nginx=1.31.9-r1'

WORKDIR /app

# Configure PHP
COPY conf.d/custom.ini /usr/local/etc/php/conf.d/

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY supervisord.conf /etc/supervisord.conf

RUN sed -i -e '/^user[[:space:]]/d' -e 's@^pid[[:space:]].*;@pid /tmp/nginx.pid;@' /etc/nginx/nginx.conf \
    && sed -i -e '/^user = www-data$/d' -e '/^group = www-data$/d' /usr/local/etc/php-fpm.d/www.conf \
    && chown -R www-data:www-data /var/cache/nginx /var/log/nginx

USER www-data:www-data
EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
