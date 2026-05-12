#!/usr/bin/env bash
# Entrypoint for the API role: php-fpm + nginx under supervisord.
set -euo pipefail

cd /var/www/html

# Wait for DB to be reachable on first boot.
ATTEMPTS=0
until php -r 'try { new PDO(getenv("DB_DSN") ?: "mysql:host=".(getenv("DB_HOST") ?: "mysql").";port=".(getenv("DB_PORT") ?: "3306").";dbname=".(getenv("DB_DATABASE") ?: "hangover"), getenv("DB_USERNAME") ?: "hangover", getenv("DB_PASSWORD") ?: "hangover"); } catch (Throwable $e) { exit(1); }'; do
  ATTEMPTS=$((ATTEMPTS+1))
  if [ $ATTEMPTS -gt 30 ]; then
    echo "DB unreachable after 30 attempts — bailing." >&2
    exit 1
  fi
  echo "Waiting for MySQL... ($ATTEMPTS)"
  sleep 2
done

# First-run helpers — safe to no-op if already done.
if [ ! -f .env ]; then
  cp .env.example .env || true
fi

# Vendor must exist for php artisan to be useful.
if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist --no-progress
fi

# Ensure storage perms.
mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache
chown -R app:app storage bootstrap/cache

# Optimize only in non-local envs.
if [ "${APP_ENV:-local}" != "local" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
