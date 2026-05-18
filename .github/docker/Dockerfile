# syntax=docker/dockerfile:1.7

# ===========================================================================
# Stage: base — shared runtime layer (PHP-FPM + nginx + supervisord)
# Used by both development (Sail) and production targets.
# ===========================================================================
FROM php:8.4-fpm-bookworm AS base

ENV DEBIAN_FRONTEND=noninteractive

# Default php-fpm pool user (overridden by `development` stage to align with host UID).
ENV SUPERVISOR_PHP_USER=www-data

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libsqlite3-dev \
        unzip \
        curl \
        ca-certificates \
    && pecl install redis \
    && docker-php-ext-install pdo_sqlite pcntl \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY .github/docker/nginx.conf        /etc/nginx/sites-available/default
COPY .github/docker/supervisord.conf  /etc/supervisor/conf.d/supervisord.conf
COPY .github/docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY .github/docker/entrypoint.sh     /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# ===========================================================================
# Stage: development — base + composer + node; source bind-mounted at runtime.
# Used by Sail (compose.yaml) with target: development.
# ===========================================================================
FROM base AS development

ARG WWWGROUP=1000
ENV SUPERVISOR_PHP_USER="sail"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

# No COPY of source — Sail bind-mounts /var/www/html at runtime.

# ===========================================================================
# Stage: vendor — composer install (cached by lockfile).
# Intermediate stage for production.
# ===========================================================================
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist

# ===========================================================================
# Stage: assets — vite/npm build in a clean node image.
# Intermediate stage for production.
# ===========================================================================
FROM node:22-bookworm-slim AS assets
WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ===========================================================================
# Stage: production — lean immutable image (no node, no composer at runtime).
# Built by CI: docker build --target production.
# ===========================================================================
FROM base AS production

COPY --from=vendor /var/www/html/vendor       /var/www/html/vendor
COPY .                                         /var/www/html
COPY --from=assets /var/www/html/public/build  /var/www/html/public/build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && rm /usr/bin/composer

ARG GIT_SHA=unknown
RUN echo "${GIT_SHA}" > /var/www/html/.version

RUN mkdir -p storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
        database \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database
