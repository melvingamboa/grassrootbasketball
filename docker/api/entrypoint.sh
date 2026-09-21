#!/usr/bin/env sh
set -eu

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ -f artisan ] && ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

if [ -f artisan ] && [ ! -e public/storage ]; then
    php artisan storage:link
fi

exec "$@"
