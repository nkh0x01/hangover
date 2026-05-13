# Hangover Platform — Deployment Plan

> Authoritative pilot-deployment plan for the `ride.365sakartvelo.com`
> subdomain. The full production topology lives in
> `docs/architecture/09-deployment-architecture.md` (AWS ECS); this
> folder is the **single-VPS pilot recipe** that gets us live in
> Tbilisi without paying for the AWS bill.

## Verdict

**cPanel shared hosting alone is not sufficient.** The platform
requires Redis, persistent queue workers, Supervisor, and a custom
WebSocket port for Laravel Reverb — none of which standard cPanel
shared plans expose.

Recommended hosting: **a single Ubuntu 22.04 / 24.04 LTS VPS** with
≥ 2 vCPU, 4 GB RAM, 40 GB SSD, public IPv4, and root SSH. cPanel can
remain in front of the registrar for DNS + email if you wish — we
just need it to point the `ride.365sakartvelo.com` `A` record at the
VPS.

Compatibility audit:

| Requirement                | Typical cPanel shared | Why we need it                                    |
|----------------------------|------------------------|---------------------------------------------------|
| PHP 8.3+                   | ✅ (MultiPHP)          | Laravel 11 minimum                                 |
| Composer (SSH)             | ✅ on most plans       | Dependency install + post-deploy hooks            |
| MySQL 8 with spatial       | ⚠️ MySQL 8 usually OK; SRID + ST_SRID support not guaranteed | Pickup/drop-off POINT columns, `active_*_lock` generated columns |
| Redis                       | ❌ rare                | Dispatch queue, GEOSEARCH driver index, idempotency, cache |
| Long-running queue workers | ❌ killed              | Ride dispatch, offer expiry, push delivery        |
| Supervisor                  | ❌ root-only           | Keeps Horizon + Reverb alive                       |
| WebSockets (Reverb)         | ❌                     | Realtime ride state to mobile clients              |
| Let's Encrypt SSL           | ✅                     | HTTPS for API + admin                              |
| Writable storage             | ✅                     | Driver document uploads, logs                      |
| Cron (1-min granularity)     | ⚠️ usually OK         | Laravel scheduler (Horizon snapshot, offer expiry mop-up) |

## Documents in this folder

| File                              | Purpose                                              |
|-----------------------------------|------------------------------------------------------|
| [`dns-records.md`](dns-records.md)               | What to set in cPanel DNS                           |
| [`vps-bring-up.md`](vps-bring-up.md)             | Fresh-VPS provisioning, stack install, hardening    |
| [`env-production-template.md`](env-production-template.md) | `.env` file with every required variable     |
| [`nginx-config.md`](nginx-config.md)             | nginx + Reverb upstream + SSL termination           |
| [`queue-workers.md`](queue-workers.md)           | Supervisor config for Horizon + Reverb              |
| [`redis-reverb-setup.md`](redis-reverb-setup.md) | Redis + Reverb install + sanity checks              |
| [`deployment-checklist.md`](deployment-checklist.md) | Pre-flight + per-deploy checklist                |
| [`rollback-plan.md`](rollback-plan.md)           | Atomic release directories + fast rollback           |

Reading order on a fresh box:
1. `dns-records.md` — point the subdomain.
2. `vps-bring-up.md` — install the stack.
3. `redis-reverb-setup.md` — bring up Redis + Reverb.
4. `nginx-config.md` — TLS + reverse-proxy + WebSockets.
5. `env-production-template.md` — copy + fill secrets.
6. `queue-workers.md` — Supervisor processes.
7. `deployment-checklist.md` — first deploy.
8. `rollback-plan.md` — print it out and pin it to the wall.

## Single-host topology (pilot)

```
                ride.365sakartvelo.com
                          │
                          ▼
              ┌───────────────────────┐
              │   nginx (TLS, ALPN)   │
              └─────┬─────┬─────┬─────┘
                    │     │     │
       /  /admin /api      │     /ws
       /api/v1/safety     │
            │              │     │
            ▼              ▼     ▼
       php-fpm 8.3   php-fpm   reverb (php artisan reverb:start)
            │              │     │
            └──────┬───────┘     │
                   ▼             │
         ┌───────────────────┐   │
         │  Laravel app      │   │
         │  /var/www/...     │   │
         └──┬───────┬────────┘   │
            │       │            │
            ▼       ▼            │
       ┌────────┐  ┌────────┐    │
       │ MySQL  │  │ Redis  │◄───┘
       │   8    │  │   7    │
       └────────┘  └────────┘
            ▲
            │ supervisor: queue-realtime, queue-default, horizon
            │
       horizon dashboard at /horizon
```

Single box runs everything for pilot. Database lives on the same
host with daily off-host backups (covered in `vps-bring-up.md` §
"Backups"). When pilot graduates we lift-and-shift to the AWS ECS
topology in `docs/architecture/09-deployment-architecture.md` — the
app code doesn't change.

## What's outside this folder

- **Mobile builds**: see `docs/phase-2.1/build-apk-runbook.md`.
- **Pilot ops procedures**: see `docs/phase-2.2/`.
- **Card-payment gateway bring-up**: see `docs/phase-2.3/payment-setup-guide.md`.
- **Safety dashboard**: see `docs/phase-2.4/admin-safety-tools.md`.

## When NOT to follow this plan

This is a pilot plan, not a production architecture. **Stop reading
and use AWS ECS** (per `docs/architecture/09-deployment-architecture.md`)
when any of these is true:

- More than ~50 concurrent active rides.
- More than ~500 daily completed rides.
- Compliance requires audited / encrypted-at-rest cloud storage.
- The Tbilisi pilot is closed and we're expanding to Batumi + Kutaisi
  + Rustavi simultaneously.

For the first 60 days of pilot, a single 4-GB VPS is plenty.
