FROM dunglas/frankenphp:1-php8.4-alpine

RUN apk add --no-cache \
    bash \
    nano \
    htop \
    bash-completion \
    oniguruma \
    libxml2 \
    curl \
    freetype \
    libgd \
    postgresql-libs \
    icu \
    gettext \
    libffi \
    rabbitmq-c \
    openssl \
    libzip \
    redis

# Install build dependencies and PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    rabbitmq-c-dev \
    linux-headers \
    freetype-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    postgresql-dev \
    gettext-dev \
    icu-dev && \
    # Configure and install extensions
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        gd \
        zip \
        pdo \
        pdo_mysql \
        mysqli \
        pdo_pgsql \
        pgsql \
        bcmath \
        exif \
        opcache \
        calendar \
        ftp \
        gettext \
        intl \
        pcntl \
        sockets \
        sysvmsg \
        sysvsem \
        sysvshm && \
    pecl install \
        xdebug \
        redis \
        amqp && \
    docker-php-ext-enable \
        xdebug \
        redis \
        amqp && \
    # Cleanup
    apk del .build-deps && \
    rm -rf /tmp/pear /var/cache/apk/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP
COPY php/conf.d/custom.ini /usr/local/etc/php/conf.d/

# Create necessary directories and set permissions
RUN mkdir -p /app \
    && chown -R www-data:www-data /app \
    && chmod -R 755 /app

WORKDIR /app

COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 80 443 443/udp

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80", "--max-requests=1000"]
