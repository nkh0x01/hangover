#!/usr/bin/env bash
# Scheduler role: invokes artisan schedule:run every minute.
set -euo pipefail
cd /var/www/html

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist --no-progress
fi

while true; do
  php artisan schedule:run --no-interaction || true
  sleep 60
done
