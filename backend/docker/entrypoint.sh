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

exec "$@"
