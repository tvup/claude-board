#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/var/www/html

# Persistent data dir (mounted as volume in production). Optional in dev.
if [ -d /data ]; then
    touch /data/database.sqlite
    ln -sf /data/database.sqlite "$APP_DIR/database/database.sqlite"
    chown www-data:www-data /data /data/database.sqlite
fi

mkdir -p "$APP_DIR/storage/logs" \
         "$APP_DIR/storage/framework/cache" \
         "$APP_DIR/storage/framework/sessions" \
         "$APP_DIR/storage/framework/views" \
         "$APP_DIR/bootstrap/cache"

# Only chown in non-dev environments. In dev with a bind-mount, chowning would
# rewrite the host's file ownership to www-data and break editing on the host.
case "${APP_ENV:-production}" in
    production|staging)
        chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database" 2>/dev/null || true
        ;;
esac

# Resolve APP_KEY: env var → cached file → one-time generate-and-cache
if [ -z "${APP_KEY:-}" ]; then
    if [ -f /data/.app_key ]; then
        APP_KEY="$(cat /data/.app_key)"
        export APP_KEY
    else
        case "${APP_ENV:-production}" in
            production|staging)
                if [ ! -d /data ] || [ ! -w /data ]; then
                    echo "FATAL: APP_KEY not set and /data not writable. Set APP_KEY in compose." >&2
                    exit 1
                fi
                APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
                echo "$APP_KEY" > /data/.app_key
                chmod 600 /data/.app_key
                export APP_KEY
                echo "==> Generated APP_KEY and cached at /data/.app_key"
                ;;
        esac
    fi
fi

case "${APP_ENV:-production}" in
    production|staging)
        echo "==> [${APP_ENV:-production}] migrate + cache config/routes/views"
        php artisan migrate --force --quiet
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        ;;
    *)
        echo "==> [${APP_ENV:-local}] dev mode — relax bind-mount dir perms, clear caches"
        # Bind-mount has host UIDs; container's php-fpm user (sail/www-data) may
        # not be the owner. Make only DIRECTORIES world-writable so files can be
        # created/replaced inside them. Do NOT chmod files — that would flip the
        # executable bit on every .php migration and pollute git status.
        find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 0777 {} + 2>/dev/null || true
        chmod 0777 "$APP_DIR/database" 2>/dev/null || true
        [ -f "$APP_DIR/database/database.sqlite" ] && chmod 0666 "$APP_DIR/database/database.sqlite" 2>/dev/null || true
        php artisan config:clear || true
        php artisan route:clear || true
        php artisan view:clear || true
        ;;
esac

echo "==> Starter supervisor (nginx + php-fpm)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
