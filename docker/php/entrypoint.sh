#!/bin/sh
set -e

cd /var/www/html

# First boot on a fresh bind mount: pull PHP deps if the app is present but unbuilt.
if [ -f composer.json ] && [ ! -d vendor ]; then
  echo "[entrypoint] vendor/ missing — running composer install…"
  composer install --no-interaction --prefer-dist
fi

# Laravel needs these writable; harmless if they already exist.
if [ -f artisan ]; then
  mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

exec "$@"
