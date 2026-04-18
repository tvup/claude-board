FROM dunglas/frankenphp:1-php8.4-alpine AS base

RUN apk add --no-cache sqlite-libs sqlite-dev unzip curl \
    && install-php-extensions pdo_sqlite pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# --- Build stage: install deps and compile assets ---
FROM base AS build

RUN apk add --no-cache nodejs npm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN composer dump-autoload --optimize \
    && npm run build

# --- Production stage: lean runtime ---
FROM base AS production

COPY --from=build /app /app

ARG GIT_SHA=unknown
RUN echo "${GIT_SHA}" > /app/.version

# Remove node_modules (not needed at runtime)
RUN rm -rf node_modules

# Ensure writable directories exist
RUN mkdir -p storage/logs \
    storage/framework/{cache,sessions,views} \
    bootstrap/cache \
    database \
    && chmod -R 775 storage bootstrap/cache database

# Generate .env with app key
RUN cp .env.example .env \
    && php artisan key:generate \
    && php artisan route:cache \
    && php artisan view:cache

COPY .github/docker/entrypoint.sh /app/docker-entrypoint.sh
RUN chmod +x /app/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/app/docker-entrypoint.sh"]
