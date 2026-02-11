# --- Stage 1: Build stage ---
FROM php:8.4-alpine AS builder

# Install build dependencies for Swoole
RUN apk add --no-cache \
    git \
    unzip \
    libstdc++ \
    libgcc \
    openssl-dev \
    pcre-dev \
    zlib-dev \
    brotli-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && pecl install swoole \
    && docker-php-ext-enable swoole

# Install Node.js for frontend assets compilation
RUN apk add --no-cache nodejs npm

WORKDIR /app

ARG SERVER_PORT=9501
ENV SERVER_PORT=${SERVER_PORT}

# Copy composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer

# Install PHP and Node dependencies
COPY composer.json composer.lock package.json package-lock.json ./
RUN php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs \
    && npm install

# Build assets and dump autoload
COPY . .
RUN npm run build
RUN php /usr/local/bin/composer dump-autoload --optimize --no-dev --classmap-authoritative

# --- Stage 2: Production runtime ---
FROM php:8.4-alpine

# Install shared libraries required by Swoole
RUN apk add --no-cache libstdc++ libgcc brotli-libs

# Copy compiled Swoole extension from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d/docker-php-ext-swoole.ini /usr/local/etc/php/conf.d/

WORKDIR /app

# Selective copy: exclude node_modules and build caches by copying only artifacts
# This helps keep the final image size optimized
COPY --from=builder /app/version.php ./version.php
COPY --from=builder /app/vendor ./vendor
COPY --from=builder /app/public ./public
COPY --from=builder /app/app ./app
COPY --from=builder /app/server.php ./server.php
COPY --from=builder /app/composer.json ./composer.json

# Only set permissions for public assets, no .env file generation here
RUN chmod -R 755 public/

EXPOSE ${SERVER_PORT}

# Start the application. Config will be read from environment variables.
CMD ["php", "server.php"]
