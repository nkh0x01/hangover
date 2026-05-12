#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist --no-progress
fi

exec php artisan reverb:start --host=0.0.0.0 --port=8080
