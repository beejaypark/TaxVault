#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    key_file=/var/www/html/storage/app/docker-app-key

    if [ ! -s "$key_file" ]; then
        umask 077
        php artisan key:generate --show > "$key_file"
    fi

    APP_KEY=$(cat "$key_file")
    export APP_KEY
fi

php artisan migrate --force
php artisan db:seed --force
php artisan taxvault:dev-admin:provision-default

# Populate the Tax News widget as soon as the web application starts. The
# scheduled sync remains the source of ongoing updates, while this initial
# sync prevents a fresh/restarted installation from showing an empty feed
# until the next scheduled run. Only the web container runs this; the queue
# worker and scheduler use the same image but should not fetch the feeds again.
if [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ] && [ "${3:-}" = "serve" ]; then
    php artisan tax-news:sync || echo "Tax news startup sync failed; continuing application startup."
fi

exec "$@"
