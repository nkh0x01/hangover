# Deployment Checklist

> Two checklists: (A) first-time bring-up (run once) and (B) the
> per-deploy checklist (run every time you ship). Print the per-
> deploy one and pin it next to your terminal during pilot.

## A. First-time bring-up

Run in order. Don't skip steps.

### Infrastructure
- [ ] VPS provisioned (Ubuntu 22.04 / 24.04, ≥ 2 vCPU / 4 GB RAM)
- [ ] DNS `A` record for `ride.365sakartvelo.com` → VPS IPv4
      (see `dns-records.md`)
- [ ] DNS propagated (verified via `dig` from your laptop)
- [ ] SSH access as non-root `hangover` user with key auth
- [ ] Root login disabled (`PermitRootLogin no`)
- [ ] UFW firewall: `22/tcp`, `80/tcp`, `443/tcp` open; everything
      else denied
- [ ] fail2ban active and watching `ssh`

### Stack
- [ ] PHP 8.3-fpm installed with `redis`, `pdo_mysql`, `bcmath`,
      `intl`, `mbstring`, `gd`, `imagick` modules
- [ ] PHP-FPM pool `hangover.conf` active; default `www.conf`
      disabled
- [ ] Composer 2.7+ available
- [ ] MySQL 8 installed; `hangover` database created with
      `utf8mb4_unicode_ci` collation
- [ ] MySQL password stored in password manager
- [ ] MySQL spatial functions work (`SELECT ST_X(ST_SRID(POINT(0,0),
      4326));` returns 0)
- [ ] Redis 7 installed; `requirepass` set; `maxmemory 512mb`,
      `allkeys-lru`
- [ ] Redis password stored in password manager
- [ ] nginx installed; default site removed
- [ ] Supervisor installed and running
- [ ] certbot installed

### App
- [ ] `/var/www/hangover/{releases,shared}` directories created,
      owned by `hangover`
- [ ] First release cloned + `composer install --no-dev`
- [ ] `/var/www/hangover/shared/.env` filled from
      `env-production-template.md`
- [ ] `.env` permissions: `600`, owned by `hangover`
- [ ] `.env` symlinked into the release dir
- [ ] `storage/` symlinked to the shared dir
- [ ] `php artisan key:generate --force`
- [ ] `php artisan migrate --force` succeeds
- [ ] `php artisan storage:link`
- [ ] `php artisan config:cache`, `route:cache`, `view:cache`,
      `event:cache`
- [ ] `/var/www/hangover/current` symlink points at the release

### Web
- [ ] nginx server block `ride.365sakartvelo.com` enabled
      (`nginx-config.md`)
- [ ] `sudo nginx -t` passes
- [ ] Let's Encrypt cert issued via certbot
- [ ] `sudo certbot renew --dry-run` succeeds
- [ ] HTTPS works: `curl -sI https://ride.365sakartvelo.com/api/v1/health`
      returns 200

### Realtime
- [ ] `REVERB_APP_KEY` + `REVERB_APP_SECRET` set in `.env`
- [ ] Supervisor running `hangover-reverb` (`reverb:start`)
- [ ] `ss -tlnp | grep 8080` shows Reverb listening
- [ ] WS upgrade succeeds:
      `curl -i -H "Connection: Upgrade" -H "Upgrade: websocket" \
       -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: $(openssl rand -base64 16)" \
       https://ride.365sakartvelo.com/app/<key>` returns
      `HTTP/1.1 101`

### Queues
- [ ] Supervisor running `hangover-horizon` (`php artisan horizon`)
- [ ] `/horizon` dashboard reachable (after admin login)
- [ ] Horizon supervisors shown: `realtime`, `default`, `low`

### Cron
- [ ] Crontab for `hangover` user contains the Laravel scheduler:
      `* * * * * cd /var/www/hangover/current/backend && php artisan schedule:run`

### Monitoring
- [ ] `SENTRY_LARAVEL_DSN` set in `.env`
- [ ] Deliberate test exception sent — confirmed in Sentry within
      60 s
- [ ] daily backup cron `/etc/cron.daily/hangover-db-dump` exists +
      executable
- [ ] off-host backup destination configured (rclone)

### Smoke tests
- [ ] `GET /api/v1/health` returns 200
- [ ] `GET /admin/login` returns 200 (Filament login screen)
- [ ] Sign in to `/admin` as the seed admin user
- [ ] First test ride (via Filament "Create test ride" Phase 2.2)
      completes end-to-end
- [ ] Test ride appears in `/admin → Operations → Pilot dashboard`
- [ ] No errors in Sentry from the test ride

### Cut-over
- [ ] `PILOT_ENABLED=true` in `.env`
- [ ] `PILOT_COHORT=tbilisi-w1`
- [ ] `PILOT_TEST_PHONES` populated with the ops + driver-tester
      phone numbers
- [ ] Mobile apps re-built against the new backend host (Phase 2.1)
- [ ] Mobile apps installed on the driver pool

When every box is ticked, you're ready for the
`docs/phase-2.2/pilot-launch-checklist.md` T-0 sequence.

---

## B. Per-deploy checklist

For every code change after the first deploy. Target time: **5
minutes** from `git push` to traffic on the new version.

### Pre-deploy
- [ ] CI green on the branch you're shipping
- [ ] Phase docs reviewed if the change touches policy
      (commission, refund, safety rules, etc.)
- [ ] DB migrations reviewed — any breaking-change migration runs
      via the **expand/contract** pattern (add columns, deploy
      code, then drop old columns in the next release — never both
      at once)
- [ ] Roll-back commit identified (the SHA of the current `current`
      symlink target)

### Deploy script

Drop this on the VPS as `/var/www/hangover/deploy.sh` (already in
the repo at `infra/deploy.sh`; copy if needed):

```bash
#!/usr/bin/env bash
#
# Atomic deploy for ride.365sakartvelo.com.
#
# Usage:
#   ./deploy.sh                           # deploys current branch HEAD
#   ./deploy.sh v0.2.0                     # deploys a tag
#   ./deploy.sh claude/scooter-platform-…  # deploys a branch
#
set -euo pipefail

ref="${1:-claude/scooter-platform-architecture-Wvmeu}"
ts="$(date +%Y%m%d%H%M%S)"
root=/var/www/hangover
release="$root/releases/$ts"

# 1. Clone the requested ref into a new release dir.
git clone --depth 1 --branch "$ref" \
  https://github.com/nkh0x01/hangover.git "$release"

cd "$release/backend"

# 2. Composer install.
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 3. Symlink shared state.
ln -sf "$root/shared/.env" "$release/backend/.env"
rm -rf "$release/backend/storage"
ln -sf "$root/shared/storage" "$release/backend/storage"

# 4. Migrate. Runs against new code, OLD release still serving.
php artisan migrate --force --no-interaction

# 5. Cache rebuilds.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Drain queues gracefully (Horizon will respawn under the new
# release once Supervisor restarts it).
php artisan horizon:terminate || true

# 7. Atomic flip.
ln -sfn "$release" "$root/current.new"
mv -Tf "$root/current.new" "$root/current"

# 8. Reload PHP-FPM (picks up new opcache).
sudo systemctl reload php8.3-fpm

# 9. Restart Reverb + Horizon onto the new release.
sudo supervisorctl restart hangover-horizon hangover-reverb

# 10. Smoke.
curl -fsS https://ride.365sakartvelo.com/api/v1/health > /dev/null

# 11. Retain last 5 releases.
ls -1dt "$root/releases/"*/ | tail -n +6 | xargs -r rm -rf

echo "Deployed: $release"
```

### Deploy

```bash
ssh hangover@ride.365sakartvelo.com
cd /var/www/hangover
./deploy.sh v0.2.0           # or branch name
```

### Post-deploy verification (do every time)

- [ ] `curl -fsS https://ride.365sakartvelo.com/api/v1/health` →
      200
- [ ] `/admin/login` loads
- [ ] `/horizon` shows green status, no spike in failed jobs
- [ ] Sentry — no new error fingerprints in the last 5 min
- [ ] First post-deploy ride end-to-end (you OR an ops shadow
      rider)
- [ ] `supervisorctl status` — both processes RUNNING

### Sanity windows

- **Day 1-7 of pilot**: deploy ≤ once per day, never in the last
  hour of service.
- **Day 8+**: deploy windows still daylight only, but increase
  cadence to twice a day if you need to.
- **Never deploy during an open P0 / P1 incident**. The deploy
  window goes red on the safety dashboard.

## Hot-fix expedited path

Sometimes a P0 needs a deploy now. The shortcut:

1. Open a branch `hotfix/<short-description>` from the current
   prod tag.
2. Single commit fixing the issue.
3. Open PR — get a second engineer to read the diff (10 min max).
4. Merge + tag `pilot-vX.Y.Z+hotfix.N`.
5. Run `./deploy.sh pilot-vX.Y.Z+hotfix.N`.
6. Post-mortem within 24 h.

Skip the docs review + phase artefacts. Add them back the next
business morning.

## Bring-up troubleshooting

### `php artisan migrate` fails with spatial errors

You're either on MySQL < 8 or on a build without SRID support.
Check `SELECT VERSION();` and confirm `ST_SRID()` works. If not,
upgrade MySQL.

### nginx 502 to `/api/*`

PHP-FPM isn't running or its socket path doesn't match. Check:

```bash
sudo systemctl status php8.3-fpm
ls -la /run/php/php8.3-fpm-hangover.sock
```

### `/horizon` returns 403

Filament guards `/horizon` behind admin users. Sign in as an admin
first; if you don't have one, run
`php artisan tinker --execute="App\Modules\Identity\Models\User::factory()->create(['type'=>'admin','phone_e164'=>'+995599000099','status'=>'active']);"`
and then log in.

### `php artisan reverb:start` fails with "address already in use"

Stale Reverb daemon. `sudo supervisorctl stop hangover-reverb`,
verify with `ss -tlnp | grep 8080`, kill the orphan if any, restart.

### Sentry shows nothing

Confirm `SENTRY_LARAVEL_DSN` is set + cached:

```bash
php artisan config:clear
php artisan tinker --execute="config('sentry.dsn');"   # must return your DSN
```

### Driver-app FCM token never lands at the backend

The mobile build is using the wrong `REVERB_APP_KEY` or `APP_URL`.
Re-build with the values in `env-production-template.md` and
re-install.

## Sign-off

Every per-deploy lands a row in the deploy log:

```bash
echo "$(date -u +%FT%TZ) $(whoami) deployed $ref → $release" \
  >> /var/log/hangover-deploys.log
```

Treat that log as your shipping record during incident reviews.
