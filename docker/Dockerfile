# Multi-stage Dockerfile for Pathfinder with PHP-FPM

# Stage 1: Build stage
FROM php:8.4-fpm-alpine AS builder

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl-dev \
    openssl-dev \
    autoconf \
    g++ \
    make \
    linux-headers \
    libevent-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    gd \
    sockets \
    && docker-php-ext-enable opcache

# Install PECL extensions (redis, event) - using latest stable versions for PHP 8.4 compatibility
RUN pecl install redis event \
    && docker-php-ext-enable redis event

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (without dev dependencies for production)
# Note: lib/ is excluded via .dockerignore, composer will pull from git instead
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# Stage 2: Runtime stage
FROM php:8.4-fpm-alpine

# Install build dependencies and runtime libraries
RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    g++ \
    make \
    libpng-dev \
    freetype-dev \
    oniguruma-dev \
    libzip-dev \
    linux-headers \
    libevent-dev \
    openssl-dev \
    && apk add --no-cache \
    libpng \
    freetype \
    libzip \
    curl \
    oniguruma \
    libcurl \
    libevent

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    gd \
    sockets \
    && docker-php-ext-enable opcache

# Install PECL extensions (redis, event) in runtime stage
# Event extension needs sockets to be loaded first, so we enable it separately with proper ordering
RUN pecl install redis event \
    && docker-php-ext-enable redis \
    && echo "extension=event.so" > /usr/local/etc/php/conf.d/zz-event.ini \
    && apk del .build-deps

# Configure PHP for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.validate_timestamps=0'; \
} > /usr/local/etc/php/conf.d/opcache.ini

RUN { \
    echo 'memory_limit=256M'; \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=10M'; \
    echo 'max_execution_time=60'; \
    echo 'max_input_vars=3000'; \
    echo 'date.timezone=UTC'; \
    echo 'session.name=pathfinder_session'; \
    echo 'error_reporting=E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
} > /usr/local/etc/php/conf.d/custom.ini

# Create app user first (Alpine uses addgroup/adduser)
RUN addgroup -S app && adduser -S -G app app

# Configure PHP-FPM to run as app user and listen on port 9000
RUN { \
    echo '[global]'; \
    echo 'error_log = /proc/self/fd/2'; \
    echo ''; \
    echo '[www]'; \
    echo 'user = app'; \
    echo 'group = app'; \
    echo 'listen = 9000'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 20'; \
    echo 'pm.start_servers = 4'; \
    echo 'pm.min_spare_servers = 2'; \
    echo 'pm.max_spare_servers = 6'; \
    echo 'access.log = /proc/self/fd/2'; \
    echo 'catch_workers_output = yes'; \
    echo 'decorate_workers_output = no'; \
} > /usr/local/etc/php-fpm.d/zz-docker.conf

# Set working directory
WORKDIR /app

# Copy vendor from builder
COPY --from=builder --chown=app:app /app/vendor ./vendor

# Copy application files
COPY --chown=app:app . .

# Create required directories with proper permissions
RUN mkdir -p tmp/cache logs export/sql && \
    chown -R app:app tmp logs export && \
    chmod -R 755 tmp logs export

# Expose PHP-FPM port
EXPOSE 9000

# Start PHP-FPM in foreground mode
CMD ["php-fpm", "--nodaemonize"]
