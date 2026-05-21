# Redis + Reverb Setup

> Redis powers the dispatch queue, GEOSEARCH driver index, cache, and
> session store. Reverb is the Pusher-protocol WebSocket broker that
> pushes ride status to mobile clients. Both run on the same VPS;
> neither talks to the outside world directly.

## Redis 7

Installed in `vps-bring-up.md` §8. This page covers tuning, sanity
checks, and the specific configuration for Hangover's three Redis
roles (queues, cache, sessions).

### `/etc/redis/redis.conf` essentials

```conf
bind 127.0.0.1 ::1
protected-mode yes
port 6379
unixsocket /var/run/redis/redis-server.sock
unixsocketperm 770
requirepass <REDIS_PASSWORD>

# Memory
maxmemory 512mb
maxmemory-policy allkeys-lru

# Persistence — both AOF + RDB; AOF for crash recovery, RDB for
# backups.
appendonly yes
appendfsync everysec
save 900 1
save 300 10
save 60 1000

# Slow log
slowlog-log-slower-than 50000
slowlog-max-len 256

# Lazy free — non-blocking deletes for large keys (e.g. the
# GEOSEARCH index).
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes
lazyfree-lazy-user-del yes
```

### Connection sanity

```bash
redis-cli -a <REDIS_PASSWORD> ping
# → PONG

redis-cli -a <REDIS_PASSWORD> INFO server | head -10
# → redis_version, uptime, etc.

redis-cli -a <REDIS_PASSWORD> CONFIG GET maxmemory-policy
# → "allkeys-lru"
```

### Three logical DBs

Hangover uses three logical Redis databases — they share the
process but are isolated for monitoring and selective flush.

| DB index | Purpose                                  | Configured via                  |
|----------|------------------------------------------|----------------------------------|
| 0        | Default — queues, GEOSEARCH, idempotency  | `REDIS_DB=0`                     |
| 1        | Cache (page cache, Filament transient)    | `REDIS_CACHE_DB=1`               |
| 2        | Sessions (admin panel)                    | `database.redis.sessions.database` |

Verify after first deploy:

```bash
redis-cli -a <REDIS_PASSWORD>
SELECT 0
KEYS hangover:nearby:*    # GEOSEARCH index keys appear once drivers go online
SELECT 1
KEYS hangover-cache:*
SELECT 2
KEYS hangover-session:*
EXIT
```

### Memory pressure

The pilot dataset is tiny — a few drivers + a few thousand rides
keeps Redis well under 50 MB. The 512 MB cap above is generous;
flag if utilisation goes above 60% before Phase 3:

```bash
redis-cli -a <REDIS_PASSWORD> INFO memory | grep used_memory_human
```

### Backups

Daily backups land on disk via the `appendonly` + RDB combination.
Off-host: copy `/var/lib/redis/dump.rdb` to S3 alongside the MySQL
backup in `vps-bring-up.md` §21:

```bash
# Add to /etc/cron.daily/hangover-db-dump:
cp /var/lib/redis/dump.rdb /var/backups/hangover/redis-$TS.rdb
```

Restoring is `sudo systemctl stop redis-server && cp redis-$TS.rdb
/var/lib/redis/dump.rdb && sudo systemctl start redis-server`. Don't
restore Redis from days-old data while the app is live — clears
GEOSEARCH index + idempotency keys, both of which will rebuild
naturally.

## Laravel Reverb

Reverb is a long-running PHP daemon — pure-PHP, no separate engine
needed. It binds `127.0.0.1:8080` and nginx upgrades upstream.

### Install (already done via composer)

`laravel/reverb ^1.4` is already in `composer.json`. Verify:

```bash
cd /var/www/hangover/current/backend
php artisan reverb:start --debug
# Daemon foreground-runs; Ctrl+C to stop.
# Confirm: "Reverb listening on 127.0.0.1:8080"
```

### Configuration (already in .env)

```ini
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=hangover
REVERB_APP_KEY=<32-hex-from-openssl>
REVERB_APP_SECRET=<32-hex-from-openssl>
REVERB_HOST=ride.365sakartvelo.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=https://ride.365sakartvelo.com,*
```

`REVERB_HOST` is what mobile clients see; `REVERB_SERVER_HOST` is
what Reverb binds. The `*` in `ALLOWED_ORIGINS` is needed for the
mobile apps — they don't send a meaningful `Origin` header. Tighten
once we're past pilot.

### Supervisor process

Covered in `queue-workers.md`. The daemon needs to restart on:
- VPS reboot (Supervisor handles).
- `.env` change (manual: `sudo supervisorctl restart reverb`).
- Code deploy (the post-deploy hook does this).

### Verify end-to-end

```bash
# 1. Reverb daemon up?
sudo supervisorctl status reverb
# → reverb RUNNING

# 2. nginx upstream resolving?
ss -tlnp | grep 8080
# → LISTEN 127.0.0.1:8080 (php)

# 3. WS handshake from outside the box:
# (run on your laptop)
curl -i \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Key: $(openssl rand -base64 16)" \
  -H "Sec-WebSocket-Version: 13" \
  https://ride.365sakartvelo.com/app/<REVERB_APP_KEY>
# → HTTP/1.1 101 Switching Protocols

# 4. Pusher diagnostic endpoint (open in browser):
# https://ride.365sakartvelo.com/apps/hangover/events
# → 401 — but proves nginx routing works.
```

### Channel authorisation

Reverb uses the `routes/channels.php` files inside each module. The
critical channels are:

```
private-driver.{ulid}     # per-driver offers
private-customer.{ulid}   # per-customer ride status
presence-ride.{ulid}      # both parties during an active ride
```

The mobile client signs the channel authorisation request with its
Sanctum bearer. The backend already auths these via the module
service providers (see `app/Modules/Riding/routes/channels.php`).

### Common Reverb failure modes

| Symptom                                    | Cause                                      | Fix                                                                |
|--------------------------------------------|---------------------------------------------|---------------------------------------------------------------------|
| 502 Bad Gateway on `/app/*`                | Daemon not running on `127.0.0.1:8080`     | `sudo supervisorctl restart reverb`                                  |
| 401 from mobile right after auth           | Reverb host/key mismatch with mobile build | Rebuild APK with matching `REVERB_APP_KEY`                          |
| 403 with "Origin not allowed"              | Mobile sends no Origin; allow list strict  | Add `*` to `REVERB_ALLOWED_ORIGINS` (pilot only)                      |
| Frequent disconnects                       | nginx `proxy_read_timeout` too low         | Already 3600s in `nginx-config.md`; verify                          |
| Messages don't propagate                    | Broadcast queue not draining               | Check `realtime` queue in Horizon                                    |

### Monitoring

Reverb logs land in `storage/logs/laravel.log` by default. For pilot,
tail it during open hours:

```bash
tail -f /var/www/hangover/shared/storage/logs/laravel-$(date +%Y-%m-%d).log \
  | grep -i "reverb\|broadcast\|channel"
```

Phase 2.5 adds Reverb-specific log channel + metrics.

## Why Redis AND Reverb (and not just one)

It's a fair question. The split is intentional:

- **Redis** holds *state* — queues, the geospatial driver index,
  short-lived ride locks. It's the system of record between
  workers.
- **Reverb** is a *transport* — it doesn't keep state. It accepts
  events from PHP-FPM workers and forwards them to subscribed
  mobile clients. The state itself lives in MySQL + Redis.

A real-time message flow for "driver accepts a ride":

```
mobile (driver) → POST /api/v1/driver/rides/{id}/accept (PHP-FPM)
   ↓
PHP-FPM → AcceptRideOffer action
   ↓
   ├─ writes ride row in MySQL
   ├─ marks Redis offer-state as accepted
   └─ broadcasts RideAccepted event via Reverb
   ↓
Reverb → push to private-customer.{customer-ulid} subscribers
   ↓
mobile (customer) → 'ride.accepted' message → UI update
```

Both pieces have to be running for the ride lifecycle to feel
responsive. Either one being down degrades the experience
(non-fatally — the API + polling fallback keep working).
