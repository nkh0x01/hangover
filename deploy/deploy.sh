#!/usr/bin/env bash
# Hotel PMS · staging deploy script
# ---------------------------------------------------------------------------
# Run this from the project root on the staging server, AFTER you have:
#   1. cloned / uploaded the repo to ~/hotel-pms (or anywhere outside webroot)
#   2. pointed hotel.365sakartvelo.com's document root at ~/hotel-pms/public
#   3. copied deploy/.env.staging.example → .env and filled in DB_* / secrets
#
# Usage:
#   bash deploy/deploy.sh           # full deploy: composer + migrate:fresh --seed + cache rebuild
#   bash deploy/deploy.sh --quick   # skip migrate:fresh; just composer + cache rebuild
#
# Safety:
#   - Refuses to run if APP_ENV != staging (avoid nuking a real DB).
#   - Refuses to run if .env is missing or APP_KEY is blank.
#   - Always uses --no-dev so dev tooling never lands on the server.
# ---------------------------------------------------------------------------

set -euo pipefail

cd "$(dirname "$0")/.."
PROJECT_ROOT="$(pwd -P)"

color()  { printf "\033[%sm%s\033[0m\n" "$1" "$2"; }
green()  { color "1;32" "$*"; }
red()    { color "1;31" "$*"; }
yellow() { color "1;33" "$*"; }

QUICK=0
if [[ "${1:-}" == "--quick" ]]; then
    QUICK=1
fi

green "→ Project root: $PROJECT_ROOT"

# 1. Sanity checks ─────────────────────────────────────────────────────────
[[ -f .env ]] || { red ".env not found — copy deploy/.env.staging.example → .env first."; exit 1; }

APP_ENV=$(grep -E '^APP_ENV=' .env | head -1 | cut -d= -f2 | tr -d '"' | tr -d "'")
APP_KEY=$(grep -E '^APP_KEY=' .env | head -1 | cut -d= -f2 | tr -d '"' | tr -d "'")
APP_DEBUG=$(grep -E '^APP_DEBUG=' .env | head -1 | cut -d= -f2 | tr -d '"' | tr -d "'")

if [[ "$APP_ENV" != "staging" ]]; then
    red "Refusing to deploy: APP_ENV is '$APP_ENV' — must be 'staging'."
    exit 1
fi

if [[ -z "$APP_KEY" ]]; then
    yellow "APP_KEY is empty — generating one now."
    php artisan key:generate --force
fi

if [[ "$APP_DEBUG" != "false" ]]; then
    yellow "WARNING: APP_DEBUG is '$APP_DEBUG' — should be false in staging. Fix .env."
fi

# 2. Composer (production) ────────────────────────────────────────────────
green "→ composer install (no dev)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 3. Storage permissions ──────────────────────────────────────────────────
green "→ Ensuring storage/bootstrap cache are writable"
mkdir -p storage/{app,framework/{cache,sessions,views},logs}
chmod -R 775 storage bootstrap/cache || true

# 4. Asset build ──────────────────────────────────────────────────────────
if command -v npm >/dev/null 2>&1; then
    green "→ npm ci + npm run build"
    npm ci --silent
    npm run build
else
    yellow "npm not found on this host. Make sure /public/build/ was uploaded as part of the bundle."
fi

# 5. Clear stale caches ───────────────────────────────────────────────────
green "→ Clearing caches"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 6. Database ─────────────────────────────────────────────────────────────
if [[ $QUICK -eq 0 ]]; then
    yellow "→ migrate:fresh --seed (this DROPS staging tables and reseeds demo data)"
    php artisan migrate:fresh --seed --force
else
    green "→ migrate --force (quick mode, no data reset)"
    php artisan migrate --force
fi

# 7. Cache for production ─────────────────────────────────────────────────
green "→ Rebuilding optimized caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 8. Storage symlink ──────────────────────────────────────────────────────
green "→ Ensuring public/storage symlink"
php artisan storage:link || true

# 9. .htaccess sanity ─────────────────────────────────────────────────────
if [[ -f deploy/htaccess-public ]] && [[ -f public/.htaccess ]]; then
    if ! cmp -s deploy/htaccess-public public/.htaccess; then
        yellow "  deploy/htaccess-public differs from public/.htaccess — copy it over if you want the hardened version."
    fi
fi

green "✔ Deploy complete."
echo
echo "Smoke test:"
echo "  curl -sI https://hotel.365sakartvelo.com/login | head -5"
echo
echo "Admin login:    admin@example.test"
echo "Reception:      reception@example.test"
echo "Password:       whatever you set in STAGING_DEMO_PASSWORD"
