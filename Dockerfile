FROM php:8.4-alpine AS builder

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

RUN apk add --no-cache nodejs npm

WORKDIR /app

ARG SERVER_PORT=9501
ENV SERVER_PORT=${SERVER_PORT}

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer

# Copy manifests only
COPY composer.json composer.lock package.json package-lock.json ./

# Install composer & npm deps
RUN php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs \
    && npm install

COPY . .

RUN npm run build
RUN php /usr/local/bin/composer dump-autoload --optimize --no-dev --classmap-authoritative

# --- Stage 2: Final Runtime ---
FROM php:8.4-alpine

# Runtime libraries
RUN apk add --no-cache libstdc++ libgcc brotli-libs

# Copy swoole
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d/docker-php-ext-swoole.ini /usr/local/etc/php/conf.d/

WORKDIR /app

# Copy everything from builder
COPY --from=builder /app /app

# Copy .env.example to .env & chmod public
RUN if [ -f .env.example ]; then cp .env.example .env; fi \
    && chmod -R 755 public/

EXPOSE ${SERVER_PORT}

# Starting
CMD ["php", "server.php"]
