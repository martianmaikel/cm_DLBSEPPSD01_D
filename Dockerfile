# ============================================================
# Stage 1: Build frontend assets
# ============================================================
FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps
COPY . .
RUN npm run build

# ============================================================
# Stage 2: Install PHP dependencies
# ============================================================
FROM composer:2 AS composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --optimize

# ============================================================
# Stage 3: Production image
# ============================================================
FROM php:8.3-fpm-alpine

# Install PHP extensions via pre-built binaries (avoids compiling from source)
RUN curl -sSLf -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql pgsql intl mbstring xml pcntl opcache redis gd

# Nginx + supervisor
RUN apk add --no-cache nginx supervisor

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-clashmonitor.ini"
COPY docker/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

# Nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor config (runs php-fpm + nginx + queue + scheduler)
COPY docker/supervisord.conf /etc/supervisord.conf

# App code
WORKDIR /var/www/html
COPY --from=composer /app .
COPY --from=frontend /app/public/build public/build

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Expose port
EXPOSE 80

# Entrypoint: migrate + cache + start supervisor
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]
