# Phase 0 + 1 — Deliverables

What landed on `claude/scooter-platform-architecture-Wvmeu` for Phase 0 (foundation) and the start of Phase 1 (Identity & onboarding). Treat this document as a tour and an onboarding cheat sheet.

## Repository tour

```
/
├── backend/                        Laravel 11 (PHP 8.3) modular monolith
├── mobile/                         Flutter monorepo (Melos): 2 apps + 7 packages
├── docs/architecture/              Locked architecture contract (11 docs)
├── docs/phase-0/                   THIS document
├── docker-compose.yml              Local stack
├── Makefile                        Dev commands
├── .github/workflows/              backend.yml, mobile.yml, terraform.yml
└── app/                            Legacy IBSU Node project (untouched)
```

## 1. Installed dependencies

### Backend (`backend/composer.json`)

| Purpose | Package |
|---|---|
| Framework | `laravel/framework ^11.30` |
| Auth | `laravel/sanctum ^4.0`, `laravel/socialite ^5.16`, `socialiteproviders/apple ^5.7` |
| Realtime | `laravel/reverb ^1.4` |
| Admin | `filament/filament ^3.2` |
| Queues | `laravel/horizon ^5.27` |
| Tinker / debug | `laravel/tinker`, `laravel/telescope` (dev only) |
| RBAC | `spatie/laravel-permission ^6.10` |
| Audit | `spatie/laravel-activitylog ^4.8` |
| Media | `spatie/laravel-medialibrary ^11.10` |
| DTO | `spatie/laravel-data ^4.11` |
| Phone | `propaganistas/laravel-phone ^5.3` |
| i18n | `mcamara/laravel-localization ^2.2` |
| Push (FCM) | `kreait/laravel-firebase ^6.0` |
| OpenAPI | `dedoc/scramble ^0.11` |
| Health | `spatie/laravel-health ^1.30` |
| Redis | `predis/predis ^2.2` (PHP redis ext loaded in Docker) |
| ULID | `symfony/uid ^7.1` |
| HTTP | `guzzlehttp/guzzle ^7.9` |
| Tests | `pestphp/pest ^3.5`, `pestphp/pest-plugin-laravel ^3.0` |
| Static | `larastan/larastan ^3.0` |
| Format | `laravel/pint ^1.18` |
| Mocking | `mockery/mockery`, `nunomaduro/collision` |

### Mobile (Melos workspace)

Per-package shown in `mobile/packages/*/pubspec.yaml` and `mobile/apps/*/pubspec.yaml`. Highlights:

| Layer | Package |
|---|---|
| State | `flutter_riverpod ^2.5.1`, `riverpod_annotation`, `riverpod_generator` |
| Routing | `go_router ^14.6.1` |
| HTTP | `dio ^5.7.0` |
| Secure storage | `flutter_secure_storage ^9.2.2` |
| Codegen | `freezed`, `freezed_annotation`, `json_serializable`, `json_annotation`, `build_runner` |
| Push | `firebase_core ^3.6.0`, `firebase_messaging ^15.1.2` |
| Logging | `logger ^2.4.0` |
| i18n | `intl ^0.19.0`, `flutter_localizations` |
| Utility | `uuid ^4.5.1` |

## 2. Environment setup

```bash
# clone + enter
git clone https://github.com/nkh0x01/hangover.git && cd hangover
git checkout claude/scooter-platform-architecture-Wvmeu

# bootstrap backend
cp backend/.env.example backend/.env
make up                # docker compose up (api, horizon, reverb, scheduler, mysql, redis, mailpit, minio)
make install           # composer install, key:generate, storage:link, migrate, db:seed

# bootstrap mobile
cd mobile
dart pub global activate melos 6.0.0
melos bootstrap
melos run gen          # runs build_runner across packages that need it
```

The seeder creates a super admin user only in local/staging — credentials in `.env`:

```env
ADMIN_EMAIL=admin@hangover.local
ADMIN_PASSWORD=change-me-on-first-login
```

## 3. Docker usage

| Command | Purpose |
|---|---|
| `make up`            | Bring up the local stack |
| `make down`          | Tear it down |
| `make logs`          | Tail all service logs |
| `make ps`            | Status |
| `make shell`         | Bash inside the api container |
| `make install`       | composer install + migrate + seed (first run or after pulling new code) |
| `make fresh`         | Drop & re-migrate (destructive) |
| `make migrate`       | Apply pending migrations |
| `make seed`          | Run seeders |
| `make pint`          | Code style |
| `make stan`          | Static analysis (level 6 in Phase 0) |
| `make test`          | Pest test suite |
| `make mobile-bootstrap` | `melos bootstrap` |
| `make mobile-gen`    | `melos run gen` |
| `make mobile-analyze` | `melos run analyze` |
| `make mobile-test`   | `melos run test` |

Service URLs (local):

| Service | URL |
|---|---|
| API | http://localhost:8000 |
| API health | http://localhost:8000/api/v1/health |
| Admin panel | http://localhost:8000/admin |
| Horizon | http://localhost:8000/horizon |
| Telescope | http://localhost:8000/telescope |
| Reverb WS | ws://localhost:8080 |
| MailPit UI | http://localhost:8025 |
| MinIO console | http://localhost:9001 (`hangover` / `hangover-secret`) |
| MySQL | localhost:3306 (`hangover` / `hangover`) |
| Redis | localhost:6379 |

## 4. CI / CD overview

Three GitHub Actions workflows live in `.github/workflows/`:

- **backend.yml** — runs on changes under `backend/**`:
  1. `setup-php` with PHP 8.3, redis/intl/mbstring/bcmath/sockets/pcov.
  2. composer cache + install.
  3. `./vendor/bin/pint --test`
  4. `./vendor/bin/phpstan analyse --memory-limit=1G`
  5. `php artisan migrate --force` against an in-job MySQL 8 + Redis service container.
  6. `./vendor/bin/pest --ci`.
- **mobile.yml** — runs on changes under `mobile/**`:
  1. flutter stable + dart pub.
  2. `melos bootstrap`, `melos run gen`.
  3. `melos run analyze`, `melos run format-check`, `melos run test`.
  4. Builds a dev-flavor APK for both apps as a smoke test.
- **terraform.yml** — `fmt -check`, `init -backend=false`, `validate` across `dev/staging/prod` env directories. Wired but the actual Terraform modules land in Phase 4.

## 5. Module overview (backend)

| Module | What it owns now | What lands later |
|---|---|---|
| `Identity` | User / UserDevice / OauthIdentity / PhoneVerification / FavoriteAddress models; OtpService + TokenIssuer; RequestOtp / VerifyOtp / RefreshToken / Logout actions; OTP controllers + routes; Filament UserResource. | Google + Apple OAuth controllers (Phase 1 part 2), step-up verification, device management endpoints. |
| `Driver` | Driver / Vehicle / DriverDocument / DriverShift models; Filament Driver + Vehicle resources; private-driver channel auth. | Approval workflow, doc upload action, online/offline, location heartbeat (Phase 1/3). |
| `Geo` | City / Zone / LiveLocation models; MapProvider contract; NearbyDriverIndex (Redis-backed). | GoogleMapsProvider + MapboxProvider impls (Phase 2). |
| `Pricing` | FareRule model. | Surge calculator, fare estimate action (Phase 2). |
| `Riding` | Ride / RideOffer / RideStatusLog / RideMessage models; RideStatus enum; Transitions map; RideStateMachine service; RideStatusChanged broadcast; private-ride channel auth; Filament RideResource. | Dispatch service, accept / arrive / start / complete actions (Phase 3). |
| `Payment` | Payment model, PaymentGateway contract, GatewayResult. | StripeGateway, Apple/Google Pay (Phase 3), local GE PSPs (Phase 6+). |
| `Wallet` | Wallet + Transaction models. | Top-up, withdraw, payout flows. |
| `Promotion` | PromoCode model. | Redemption rules + admin CRUD (Phase 4). |
| `Rating` | Rating model. | Post-trip rating action, denormalization job (Phase 3). |
| `Communication` | SmsGateway contract; Null + Twilio drivers wired by `config/sms.php`. | FCM channel + templates (Phase 4). |
| `Support` | SupportTicket + SupportMessage models. | Ticket CRUD, SOS, fraud handling (Phase 4). |
| `Cms` | CmsPage + AppConfig models. | Admin CRUD + feature-flag runtime (Phase 4). |

Cross-module:

- `app/Support/` — Ulid, Money, Geo\Point, IdempotencyStore, JsonErrorRenderer, DomainException base.
- `app/Http/Middleware/` — LogRequestId, LocalizeRequest, EnsureDeviceBound, EnforceAppVersion, EnsureIdempotency.
- `app/Providers/` — App, Auth, Broadcast, Event, Horizon, Module, Telescope service providers + `Filament/AdminPanelProvider`.
- Rate limiters (named) live in `AppServiceProvider::configureRateLimiters()` (auth.otp, auth.verify, auth.refresh, api.default, api.write, driver.location, rides.create, support.create, sos.create).

## 6. Migration overview

25 migrations, all timestamped `0001_01_01_*` so the install order is deterministic. Schema highlights:

- **`users`** — ULID + phone + email + locale + status + soft delete; sparse unique on email.
- **`user_devices`** — `(user_id, device_uuid)` unique; FCM + VoIP tokens; revocation timestamp.
- **`personal_access_tokens`** — Sanctum default; we encode `pat:{platform}:{device_uuid}` in `name` for device-binding enforcement.
- **`drivers`** — extends `users`; encrypted `id_number_encrypted` / `iban_encrypted`; status enum.
- **`vehicles`** — soft-deleted; JSON photos.
- **`cities` / `zones`** — `POINT SRID 4326` center, `POLYGON SRID 4326` bounding / zones, spatial indexes.
- **`live_locations`** — append-only; spatial POINT; `(driver_id, recorded_at)` index.
- **`rides`** — central entity; spatial pickup/dropoff; ENUM status; **`active_driver_lock`** and **`active_customer_lock`** generated columns with unique indexes — DB-level guarantee against two active rides per user/driver.
- **`ride_offers`** — unique (`ride_id`,`driver_id`) so duplicate offers are impossible.
- **`ride_status_logs`** — append-only audit trail.
- **`wallets` + `transactions`** — append-only ledger pattern with `balance_after` snapshot; `transactions.ulid` for idempotent external references.
- **`payments` / `refunds` / `payouts`** — gateway-agnostic columns + `raw_response` JSON.
- **`promo_codes` / `promo_redemptions`** — unique (`promo_code_id`,`user_id`,`ride_id`).
- **`notifications`** — Laravel's polymorphic table.
- **`notification_preferences`** — per-user toggles.
- **`sms_log`** — cost and delivery tracking.
- **`support_tickets` / `support_messages`** — ticketing.
- **`fraud_flags`** — system + admin-raised.
- **`sos_events`** — spatial POINT + status workflow.
- **`activity_log`** — Spatie audit table.
- **`cms_pages` / `app_configs`** — runtime config + feature flags.

Seeders:

- `RolesAndPermissionsSeeder` — 22 permissions across `web` + `sanctum` guards; 7 roles wired to the matrix from `docs/architecture/08-admin-panel-structure.md`.
- `CitiesSeeder` — Tbilisi with center POINT.
- `SuperAdminSeeder` — local/staging-only super admin from `.env`.

## 7. Verifying the install

```bash
# Backend
make install
curl http://localhost:8000/api/v1/health
# → {"data":{"status":"ok","service":"Hangover Mobility","time":"..."}}

curl http://localhost:8000/api/v1/version
curl http://localhost:8000/api/v1/config

# Auth (dev SMS driver logs the OTP to storage/logs/sms-*.log)
curl -X POST http://localhost:8000/api/v1/auth/otp/request \
     -H 'Content-Type: application/json' \
     -d '{"phone":"+995555123456","purpose":"signup"}'

# Tail the SMS log to read the dev-driver code
tail -f backend/storage/logs/sms-$(date +%F).log

curl -X POST http://localhost:8000/api/v1/auth/otp/verify \
     -H 'Content-Type: application/json' \
     -d '{"phone":"+995555123456","code":"<the-code>","purpose":"signup","device_uuid":"00000000-0000-4000-8000-000000000001","platform":"ios","app_version":"1.0.0"}'

# Admin panel
open http://localhost:8000/admin  # creds from .env
```

```bash
# Mobile
cd mobile
melos run analyze
melos run test

cd apps/customer_app
flutter run --flavor dev -t lib/main_dev.dart
```

## 8. Quality gates that already run

- `composer pint` (Laravel formatting, with strict_types).
- `composer stan` (Larastan level 6 — tightening to 8 happens once ride logic is in).
- `composer test` (Pest unit + feature; HealthCheckTest, MoneyTest, RideTransitionsTest land green).
- `melos run analyze` + `melos run format-check` + `melos run test`.

## 9. Recommended next phase

**Phase 1 part 2 + Phase 2** — what to do next, in priority order:

1. **OAuth controllers + Apple/Google verifiers** — finish the Identity module per `docs/architecture/06-authentication-flow.md` §6.4–6.5.
2. **Driver onboarding API** — `POST /driver/onboarding/documents`, `GET /driver/onboarding/status`, approval/rejection admin actions emitting events.
3. **Geo proxy endpoints** — `/customer/places/autocomplete`, `/customer/places/reverse-geocode`, `/customer/drivers/nearby` backed by `NearbyDriverIndex`.
4. **Pricing module flesh-out** — Filament resources for FareRule + Zone; surge multiplier admin board; `POST /customer/rides/estimates` action with the FareLockService.
5. **Mobile: complete auth UI** — OTP screen with the Riverpod-driven controller, name setup, profile screen wired through the AuthRepository.
6. **Mobile: maps integration** — GoogleMapsProvider concrete implementation; pickup/dropoff selection sheets.

Phase 3 (full dispatch + realtime ride lifecycle) follows. See `docs/architecture/10-development-roadmap.md` for the full plan.

## 10. Known caveats

- The map provider abstraction is in place but no concrete impl is registered — calling `MapProvider` will throw "not configured" until Phase 2.
- OAuth controllers exist on paper only; phone OTP is the working auth path.
- `vendor/` is not committed; first `make install` resolves Composer + npm deps.
- iOS/Android native scaffolding (Xcode project, Gradle config) is not committed — Phase 1 will run `flutter create --org ge.hangover --project-name customer_app .` inside each app directory to generate it. The `pubspec.yaml`, Dart source, flavor entrypoints, and test files are all in place.
- The legacy `/app` Node project remains; archive when convenient.
