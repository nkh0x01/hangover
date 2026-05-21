# Queue Workers + Supervisor Setup

> Supervisor keeps Horizon (queue workers) and Reverb (WebSocket
> broker) alive. Both processes restart on crash; Horizon scales
> workers up/down based on queue depth.

## Why Horizon, not raw `queue:work`

We use Laravel Horizon (`laravel/horizon`) for queue workers. Two
reasons:

1. **Auto-scaling**: Horizon spawns more processes for the `realtime`
   queue when offer dispatch backs up and contracts when idle. The
   raw `queue:work` is a single fixed process per Supervisor entry.
2. **Dashboard**: `/horizon` URL shows live throughput, failed jobs,
   and per-queue depth. Critical during pilot debugging.

Horizon itself is just `php artisan horizon` — a supervisor of
queue:work workers. We supervise *Horizon*, Horizon supervises
*workers*.

## Horizon configuration

`config/horizon.php` is already shaped for the platform (Phase 1.5).
Confirm in the running config:

```bash
cd /var/www/hangover/current/backend
php artisan horizon:list
```

Expected supervisors:

| Supervisor | Queues               | Min processes | Max processes | Notes                                  |
|------------|----------------------|----------------|----------------|----------------------------------------|
| `realtime` | `realtime`           | 1              | 3              | DispatchRide, OfferRide, SendOfferPush |
| `default`  | `default`            | 1              | 2              | One-off + low-priority background jobs |
| `low`      | `payments,notifications` | 1          | 2              | Payouts, comms                          |

(Exact numbers depend on commit; check `config/horizon.php`.)

## Supervisor — Horizon process

`/etc/supervisor/conf.d/hangover-horizon.conf`:

```ini
[program:hangover-horizon]
process_name=%(program_name)s
command=php /var/www/hangover/current/backend/artisan horizon
autostart=true
autorestart=true
user=hangover
redirect_stderr=true
stdout_logfile=/var/log/supervisor/hangover-horizon.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
```

`stopwaitsecs=3600` lets Horizon drain in-flight jobs cleanly on
deploy. The deploy script sends `php artisan horizon:terminate`
which signals Supervisor to wait up to an hour for the workers to
finish (in practice ≤ 60 s for our job mix).

## Supervisor — Reverb process

`/etc/supervisor/conf.d/hangover-reverb.conf`:

```ini
[program:hangover-reverb]
process_name=%(program_name)s
command=php /var/www/hangover/current/backend/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=hangover
redirect_stderr=true
stdout_logfile=/var/log/supervisor/hangover-reverb.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=10
```

`stopwaitsecs=10` — Reverb has no in-flight state to drain; just kill
it cleanly.

## Wire it up

```bash
sudo supervisorctl reread       # discover the new files
sudo supervisorctl update       # start them
sudo supervisorctl status
# Expected:
#   hangover-horizon                 RUNNING   pid 1234, uptime 0:00:05
#   hangover-reverb                  RUNNING   pid 1235, uptime 0:00:05
```

## Health checks

```bash
# 1. Horizon dashboard:
# https://ride.365sakartvelo.com/horizon
# Sign in as an admin user. Should show 0-5 active processes.

# 2. Workers actually consuming:
redis-cli -a <REDIS_PASSWORD> LLEN queues:realtime
# After traffic: bounces 0..N..0 — never sustained > 10.

# 3. Reverb listening:
ss -tlnp | grep 8080
# tcp LISTEN 127.0.0.1:8080 users:(("php",pid=...))

# 4. End-to-end: queue a test broadcast.
# (run on the VPS as hangover)
cd /var/www/hangover/current/backend
php artisan tinker --execute="event(new \App\Modules\Riding\Events\TestPing('hello'));"
# Watch the dashboard tick.
```

## Deploy-time interaction

The deploy script (in `deployment-checklist.md`) does this in order:

1. Build the new release dir.
2. Run migrations (against the new code, but with the OLD release
   still serving traffic).
3. `php artisan horizon:terminate` — signals workers to finish
   current job + exit. Supervisor will respawn them against the new
   release once we flip the symlink.
4. Atomic symlink flip — `current` now points to the new release.
5. PHP-FPM reload (picks up new opcache).
6. `sudo supervisorctl restart hangover-horizon hangover-reverb` —
   reset both processes onto the new code.

The whole flip is sub-second of user-visible downtime.

## Failed job handling

Failed queue jobs land in `failed_jobs` table.

```bash
php artisan queue:failed             # list
php artisan queue:retry 5            # retry by id
php artisan queue:retry all          # retry everything
php artisan queue:forget 5           # delete (after manual fix)
php artisan queue:flush              # nuke all (be careful)
```

Horizon dashboard exposes the same — pick whichever ops finds
faster.

## Auto-restart on memory bloat

PHP queue workers leak memory over time. Horizon already restarts
each worker after N memory MB OR N jobs (see `config/horizon.php`
`memory` + `tries` keys). If you tune them up:

```php
// config/horizon.php
'environments' => [
    'production' => [
        'realtime' => [
            'memory' => 192,    // MB
            'tries' => 3,
            // ...
        ],
    ],
],
```

Restart Horizon after config changes:

```bash
sudo supervisorctl restart hangover-horizon
```

## Logs

```bash
# Supervisor process logs (stderr + stdout combined):
tail -f /var/log/supervisor/hangover-horizon.log
tail -f /var/log/supervisor/hangover-reverb.log

# Laravel application logs:
tail -f /var/www/hangover/shared/storage/logs/laravel-$(date +%Y-%m-%d).log

# Queue-specific channel:
tail -f /var/www/hangover/shared/storage/logs/dispatch-$(date +%Y-%m-%d).log
```

During pilot, keep all three open in tmux.

## Watchdog / external alarms

A Supervisor process going `FATAL` (won't restart) is a P1 incident.
Catch it early:

```bash
# /etc/cron.d/hangover-watchdog
*/2 * * * * hangover \
  if /usr/bin/supervisorctl status hangover-horizon | grep -q FATAL; then \
    echo "Horizon FATAL" | mail -s "Hangover Horizon DOWN" ops@365sakartvelo.com; \
  fi
```

For pilot, this email-alert is enough. Phase 2.5 wires Sentry alerts
+ Slack page on the same trigger.

## Troubleshooting

### Symptom: jobs queue up, never run

```bash
sudo supervisorctl status hangover-horizon
# If STOPPED or FATAL:
sudo supervisorctl tail -f hangover-horizon stderr
# Common causes:
#   - Missing PHP extension (php-redis): apt install php8.3-redis
#   - Wrong .env file or .env not readable: chmod 600 + chown
#   - Stale opcache: sudo systemctl restart php8.3-fpm
```

### Symptom: jobs run but don't execute the new code after deploy

```bash
sudo supervisorctl restart hangover-horizon
# Or, more graceful:
cd /var/www/hangover/current/backend && php artisan horizon:terminate
# Supervisor auto-respawns with the new release.
```

### Symptom: Reverb crashes immediately

```bash
sudo supervisorctl tail -f hangover-reverb stderr
# Common: port 8080 already in use → check `ss -tlnp | grep 8080`,
# kill the orphan process.
```

### Symptom: "Class 'Laravel\Horizon\HorizonServiceProvider' not found"

You're missing `composer require laravel/horizon` (already in
composer.json) or `composer install` was run without `--no-dev`
flipping the wrong deps. Re-run `composer install --no-dev
--optimize-autoloader` on the release.

## When pilot graduates

For AWS ECS production, Horizon + Reverb each become their own ECS
task family (one per role, autoscaled). The Supervisor config above
is replaced by ECS task definitions; the rest of the Laravel
configuration stays identical. See
`docs/architecture/09-deployment-architecture.md`.
