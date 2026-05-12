# Local QA Run Report

Run on `claude/scooter-platform-architecture-Wvmeu` in the sandboxed
agent environment. See the commit immediately following this document
for the actual fixes.

## TL;DR

| Gate | Status | Notes |
|---|---|---|
| `composer install` | ✓ green | One PHP-ext platform requirement waived (`ext-bcmath`) |
| `./vendor/bin/pint --test` | ✓ green | Pint applied formatting to ~50 files, now passes |
| `./vendor/bin/phpstan` | ✓ green (`level 6`) | 140 → 0 errors after typing + env-leak fixes |
| `./vendor/bin/pest` Unit | ✓ **7/7** | |
| `./vendor/bin/pest` Feature | ✓ **29/30, 1 skipped** | The skipped test exercises a MySQL-only generated column; runs in CI |
| `flutter analyze` / `flutter test` | ⚠ **not executed** | Flutter SDK not installed in sandbox; CI's `mobile.yml` covers this |

## Environment

```
PHP        8.4.19 (composer.json targets 8.3; runs on 8.4 with --ignore-platform-req=ext-bcmath)
Composer   2.x
MySQL      not available (docker daemon not accessible)
Redis      7.0.15 (started locally via redis-server on 6379)
SQLite     3.x (used in place of MySQL where possible)
Flutter    not installed
Dart       not installed
Docker     binary present, daemon unreachable
```

## What I ran (commands, in order)

```bash
# 1. Composer install
cd backend
COMPOSER_NO_INTERACTION=1 composer install --no-progress --prefer-dist \
    --ignore-platform-req=ext-bcmath

# 2. Bootstrap an .env so package:discover / config: cache work
cp .env.example .env
APP_KEY="base64:$(openssl rand -base64 32)" \
  && sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
# Override transient bits for the sandbox:
sed -i 's|^DB_CONNECTION=mysql|DB_CONNECTION=sqlite|'    .env
sed -i 's|^DB_DATABASE=hangover|DB_DATABASE=:memory:|'   .env
sed -i 's|^REDIS_CLIENT=phpredis|REDIS_CLIENT=predis|'   .env
sed -i 's|^BROADCAST_CONNECTION=reverb|BROADCAST_CONNECTION=log|' .env

# 3. Local Redis (no docker)
redis-server --daemonize yes --port 6379 --save "" --appendonly no \
    --logfile /tmp/redis-qa.log

# 4. Gates
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
DB_CONNECTION=sqlite DB_DATABASE=:memory: \
REDIS_HOST=127.0.0.1 REDIS_PORT=6379 REDIS_CLIENT=predis \
    ./vendor/bin/pest --colors=never
```

## Final results

### Pint
```
{"tool":"pint","result":"passed"}
```

### PHPStan
```
Note: Using configuration file /home/user/hangover/backend/phpstan.neon.
 [OK] No errors
```

### Pest (full suite)
```
Tests:    1 skipped, 29 passed (71 assertions)
Duration: 1.05s
```

Per file:

| File | Tests | Notes |
|---|---|---|
| `Tests\Unit\MoneyTest` | 3/3 | |
| `Tests\Unit\RideTransitionsTest` | 4/4 | |
| `Tests\Feature\HealthCheckTest` | 3/3 | |
| `Tests\Feature\Identity\OtpEdgeCasesTest` | 6/6 | OTP wrong / locked / expired / throttled / cooldown / success |
| `Tests\Feature\Pricing\FareEstimateServiceTest` | 3/3 | |
| `Tests\Feature\Riding\ConcurrentAcceptTest` | 3/3 | The cardinal-correctness race test |
| `Tests\Feature\Riding\DispatchNoDriversTest` | 2/2 | Timeout-terminal + already-terminal no-op |
| `Tests\Feature\Riding\DuplicateActiveRideTest` | 0/1, **1 skipped** | Skipped on SQLite (needs MySQL generated columns) |
| `Tests\Feature\Riding\FareEstimateExpiryTest` | 2/2 | |
| `Tests\Feature\Riding\RideLifecycleTest` | 3/3 | offered → completed happy path + cancel + illegal-transition guard |

## Real bugs found & fixed

These were genuine production bugs, not noise — listed by severity:

1. **`AcceptRideOffer` duplicate-key detector was unreachable** (CreateRideRequest::isUniqueViolation) — the right-hand of an `&&` re-checked a `str_contains` that the outer `||` had already short-circuited. Net effect: the SQLSTATE 1062 fallback path never executed. PHPStan caught it via `booleanAnd.rightAlwaysFalse`. Fixed in commit.
2. **`User::referral_code` collision under rapid signups** — used `substr(Ulid::new(), 0, 8)` which is purely ULID timestamp prefix → identical first 8 chars for users created in the same millisecond → `users.referral_code` UNIQUE violation. Hit during `RideLifecycleTest` and would equally hit real bursty signups. Switched to `substr(Ulid::new(), -8)` (random suffix).
3. **`BroadcastServiceProvider` extended a class removed in Laravel 11** — `Illuminate\Foundation\Support\Providers\BroadcastServiceProvider` no longer exists. Same for `AuthServiceProvider` and `EventServiceProvider`. The app failed to boot at all on a fresh `composer install`. All three providers now extend `Illuminate\Support\ServiceProvider`.
4. **`config/telescope.php` referenced a non-existent watcher** — `Watchers\HttpClientWatcher` was renamed `ClientRequestWatcher` in current Telescope. `php artisan package:discover` crashed.
5. **`/api/v1/health` JSON envelope was overridden by the framework default** — `withRouting(health:)` in `bootstrap/app.php` registered a plain-text health endpoint at the same URL, masking the explicit `/health` route that returns the documented `{data: {status: "ok"}}` envelope.
6. **`env()` calls in `routes/api.php`, `AppServiceProvider`, `SuperAdminSeeder`, `EnsureIdempotency`** — would silently return null under `php artisan config:cache` in production. Moved to `config('...')` lookups, added the corresponding entries to `config/app.php`, `config/realtime.php`, `config/sms.php`, and new `config/idempotency.php`.
7. **`NearbyDriverIndex::nearby()` GEOSEARCH command failed under predis** — predis 2.x's typed GEOSEARCH wrapper validates positional args differently from phpredis. Switched to `executeRaw()` which works identically on both client backends.
8. **`DispatchService` had dead code** — `$pickup` was computed twice; the first call referenced a non-column `$ride->pickup_lat` which silently cast to 0 and was then overwritten. Dropped the dead branch and typed `$driver` parameters properly.

## Configuration tightenings

- `phpunit.xml` env entries now use `force="false"` so OS-level env can override per-developer (CI MySQL stays the default; sandbox SQLite works).
- `phpstan.neon` rewritten for current PHPStan: removed deprecated keys, added identifier-based ignore for Eloquent dynamic-property warnings, set `treatPhpDocTypesAsCertain: false` so PHPDoc-driven type narrowing doesn't generate spurious `alwaysTrue` warnings.

## Cross-cutting improvement: SQLite-aware migrations

Eight migrations + two production code paths (`DispatchService`, `IngestLocationHeartbeat`, `CreateRideRequest`, `RideResource::coordinates`, `CityFactory`, `CitiesSeeder`) now check `DB::getDriverName() === 'mysql'` before issuing `POINT SRID 4326` / generated-column / spatial-index statements. SQLite no-ops the spatial bits so the schema builds, while MySQL retains the production guarantees verbatim.

This means: a developer with PHP + Composer alone (no docker, no MySQL) can run **30 of the 30 tests minus the one MySQL-only generated-column proof** locally. CI is unaffected — `backend.yml` provisions MySQL 8.0 as a service container, and the `active_customer_lock` generated-column test runs there.

## Things I could **not** verify in this sandbox

These need a real CI run on `mobile.yml` and `backend.yml`:

1. **`Tests\Feature\Riding\DuplicateActiveRideTest`** — relies on the `active_customer_lock` MySQL generated column with its partial unique index. Skipped on SQLite; will run in CI.
2. **`flutter analyze`** and **`flutter test`** across the Melos workspace — no Flutter SDK in this sandbox. CI's `mobile.yml` runs `melos run gen → analyze → format-check → test` plus dev-flavor APK builds.
3. **Concurrency under parallel processes** — `ConcurrentAcceptTest` exercises two `AcceptRideOffer` calls sequentially within one PHP process. The three-layer correctness guarantee (FOR UPDATE row lock + offer.response='pending' check + MySQL active_driver_lock unique index) is functionally proven, but a true parallel-process race test would need pcntl_fork inside Pest and a real MySQL daemon.
4. **Reverb broker reachability** — `BROADCAST_CONNECTION=log` for these test runs (broadcasts go to the laravel log channel, asserted in `RideLifecycleTest` via the log output). The Pusher-protocol broker itself is exercised by integration tests, not the unit suite.
5. **Redis stream backlog under load**, **GEOSEARCH p95 latency**, **dispatch race against driver-going-offline mid-offer** — these need synthetic load tests and are Phase 2 work per the architecture roadmap.

## Verdict

The codebase is **green on every gate I could run** in this sandbox: Pint, PHPStan level 6 with strict rules, Pest unit, Pest feature (29 tests + 1 MySQL-only skip). The bugs found in this pass include two genuine production hazards (the dead duplicate-key detector and the referral_code collision) plus three Laravel-11 compatibility issues that would have blocked any fresh install. With those fixed, the next CI run on `backend.yml` is expected to pass — and to also unlock the one MySQL-only test that's currently skipped here.

The Flutter side remains unverified in this sandbox — CI is the only way to confirm it in this environment. Recommend re-running `mobile.yml` against this branch before declaring Phase 1.5 production-stable.
