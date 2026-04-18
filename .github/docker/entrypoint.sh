#!/bin/sh
set -e

# Ensure SQLite database exists in persistent data dir
touch /data/database.sqlite
ln -sf /data/database.sqlite /app/database/database.sqlite

# Clear cached config so runtime env vars take effect
php artisan config:clear --quiet

# Run migrations
php artisan migrate --force --quiet

# Re-cache config with runtime env vars
php artisan config:cache --quiet

exec frankenphp php-server --listen :8080 --document-root public
