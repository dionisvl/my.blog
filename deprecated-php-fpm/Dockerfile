FROM php:8.4.10-fpm-alpine3.21

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    # Base utils
    bash \
    nano \
    htop \
    bash-completion \
    # PHP 8.4 extensions (precompiled, fast install)
    php84-common \
    php84-mysqli \
    php84-pdo_mysql \
    php84-mbstring \
    php84-curl \
    php84-xml \
    php84-bcmath \
    php84-exif \
    php84-pdo_pgsql \
    php84-pgsql \
    php84-opcache \
    php84-calendar \
    php84-ctype \
    php84-ffi \
    php84-fileinfo \
    php84-ftp \
    php84-gettext \
    php84-intl \
    php84-phar \
    php84-posix \
    php84-session \
    php84-shmop \
    php84-sockets \
    php84-sysvmsg \
    php84-sysvsem \
    php84-sysvshm \
    php84-tokenizer \
    php84-pcntl \
    php84-redis \
    # Runtime libraries
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
    libzip

# Install GD, ZIP, MySQL and PECL extensions
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    rabbitmq-c-dev \
    linux-headers \
    freetype-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libzip-dev && \
    # Configure and install extensions
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        gd \
        zip \
        pdo \
        pdo_mysql \
        mysqli && \
    # Install PECL extensions
    pecl install \
        xdebug \
        amqp && \
    docker-php-ext-enable \
        xdebug \
        amqp && \
    apk del .build-deps && \
    rm -rf /tmp/pear

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP
COPY php/conf.d/custom.ini /usr/local/etc/php/conf.d/
COPY php/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/

# Setup supervisor
COPY supervisor/supervisord.conf /etc/supervisord.conf

# Copy nginx configuration
COPY nginx/app.conf /etc/nginx/http.d/default.conf

# Create necessary directories and set permissions
RUN mkdir -p /run/nginx /var/log/supervisor /app \
    && chown -R www-data:www-data /app \
    && chmod -R 755 /app

WORKDIR /app

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
