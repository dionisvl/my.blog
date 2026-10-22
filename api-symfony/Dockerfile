ARG PHP_VERSION=8.4.27
FROM dunglas/frankenphp:php${PHP_VERSION} AS base

RUN install-php-extensions \
    pdo_mysql \
    pdo_pgsql \
    intl \
    gd \
    zip

FROM base AS apcu_builder
RUN apt-get update \
 && apt-get install -y --no-install-recommends autoconf gcc make libc6-dev pkg-config \
 && curl -fsSL --retry 3 --connect-timeout 10 https://pecl.php.net/get/apcu-5.1.28.tgz -o /tmp/apcu.tgz \
 && echo "ca9c1820810a168786f8048a4c3f8c9e3fd941407ad1553259fb2e30b5f057bf  /tmp/apcu.tgz" | sha256sum -c - \
 && mkdir -p /tmp/apcu-src \
 && tar xzf /tmp/apcu.tgz -C /tmp/apcu-src \
 && cd /tmp/apcu-src/apcu-5.1.28 \
 && phpize \
 && ./configure --quiet \
 && make -j1 \
 && make install \
 && cp "$(php-config --extension-dir)/apcu.so" /tmp/apcu.so

FROM base AS ready
COPY --from=apcu_builder /tmp/apcu.so /tmp/apcu.so
RUN cp /tmp/apcu.so "$(php-config --extension-dir)/apcu.so" \
 && docker-php-ext-enable apcu \
 && rm /tmp/apcu.so

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

FROM ready AS prod

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
COPY . /app

EXPOSE 80 443
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

FROM ready AS dev
RUN install-php-extensions xdebug
WORKDIR /app
