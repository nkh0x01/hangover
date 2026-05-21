# 03 — Laravel Backend Structure

## 3.1 High-level layout

A **modular monolith**: one Laravel 11 application, but business code lives under `app/Modules/<Module>/` rather than the flat default. Framework glue (`app/Http/Kernel.php`, providers, etc.) stays in the conventional locations.

```
backend/
├── app/
│   ├── Console/                        # only artisan command shells; delegate to module commands
│   ├── Exceptions/
│   │   └── Handler.php                 # uniform JSON error envelope for /api/*
│   ├── Http/
│   │   ├── Controllers/                # only thin glue controllers when not module-owned (rare)
│   │   ├── Middleware/
│   │   │   ├── EnsureDeviceBound.php
│   │   │   ├── EnforceAppVersion.php
│   │   │   ├── LogRequestId.php
│   │   │   └── Localization.php
│   │   └── Resources/                  # only shared (User, City). Module-specific resources live in modules.
│   ├── Modules/
│   │   ├── Identity/
│   │   ├── Driver/
│   │   ├── Geo/
│   │   ├── Pricing/
│   │   ├── Riding/
│   │   ├── Payment/
│   │   ├── Wallet/
│   │   ├── Promotion/
│   │   ├── Rating/
│   │   ├── Communication/
│   │   ├── Support/
│   │   └── Cms/
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   ├── BroadcastServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   ├── ModuleServiceProvider.php   # registers each Modules\*\Providers\*ServiceProvider
│   │   └── TelescopeServiceProvider.php (local/staging only)
│   └── Support/                        # cross-module primitives (no business logic)
│       ├── Concerns/
│       ├── Dto/Dto.php                 # base DTO with from-array + validation
│       ├── Ulid.php
│       ├── Money.php
│       ├── Geo/Point.php
│       └── Idempotency/IdempotencyStore.php
├── bootstrap/
├── config/
│   ├── modules.php                     # list of active module providers
│   ├── geo.php                         # map provider config
│   ├── pricing.php
│   ├── realtime.php
│   ├── payments.php
│   └── ... (Laravel stock + above)
├── database/
│   ├── factories/                      # shared factories only
│   ├── migrations/                     # canonical timestamps; modules contribute via publish
│   └── seeders/
├── lang/                               # ka/, en/, ru/
├── public/
├── resources/
│   └── views/                          # Filament + emails only; API has no views
├── routes/
│   ├── api.php                         # delegates to Modules\<X>\routes\api.php via Route::group
│   ├── api_admin.php
│   ├── channels.php
│   ├── console.php
│   └── web.php                         # admin panel only
├── storage/
├── tests/
│   ├── Feature/
│   │   └── Modules/<Module>/...
│   ├── Unit/
│   └── Pest.php
├── .env.example
├── artisan
├── composer.json
└── phpunit.xml
```

## 3.2 Anatomy of a module

Each module is self-contained. The Riding module is the canonical example:

```
app/Modules/Riding/
├── Actions/                # single-purpose application services (CQRS-style commands)
│   ├── CreateRideRequest.php
│   ├── DispatchRide.php
│   ├── AcceptRideOffer.php
│   ├── RejectRideOffer.php
│   ├── DriverArriving.php
│   ├── DriverArrived.php
│   ├── StartTrip.php
│   ├── CompleteTrip.php
│   └── CancelRide.php
├── Contracts/              # interfaces exposed to other modules
│   ├── RideRepository.php
│   └── DispatcherInterface.php
├── Dto/
│   ├── RideRequestData.php
│   ├── RideOfferData.php
│   └── RideCompletionData.php
├── Events/
│   ├── RideRequested.php
│   ├── RideOffered.php
│   ├── RideAccepted.php
│   ├── RideStatusChanged.php
│   ├── RideCompleted.php
│   └── RideCancelled.php
├── Exceptions/
│   ├── RideNotOfferableException.php
│   ├── InvalidRideTransitionException.php
│   └── NoDriversAvailableException.php
├── Http/
│   ├── Controllers/
│   │   ├── Customer/RideController.php
│   │   ├── Customer/RideMessageController.php
│   │   ├── Driver/RideOfferController.php
│   │   └── Driver/RideController.php
│   ├── Requests/
│   │   ├── CreateRideRequest.php
│   │   ├── CancelRideRequest.php
│   │   └── AcceptOfferRequest.php
│   ├── Resources/
│   │   ├── RideResource.php
│   │   ├── RideListResource.php
│   │   └── RideOfferResource.php
│   └── Policies/
│       └── RidePolicy.php
├── Jobs/
│   ├── OfferRideToNextDriver.php
│   ├── ExpireRideOffer.php
│   ├── TimeoutSearchingRide.php
│   └── FinalizeRideAccounting.php
├── Listeners/
│   ├── BroadcastRideStatusChanged.php
│   ├── NotifyCustomerOfDriverAccepted.php
│   ├── PushDriverArrivedNotification.php
│   └── WriteRideStatusLog.php
├── Models/
│   ├── Ride.php
│   ├── RideOffer.php
│   ├── RideStatusLog.php
│   ├── RideRoutePoint.php
│   └── RideMessage.php
├── Notifications/
│   ├── RideAcceptedPush.php
│   ├── DriverArrivingPush.php
│   └── RideCompletedPush.php
├── Policies/
├── Providers/
│   ├── RidingServiceProvider.php       # binds Contracts, registers routes/events/policies
│   └── RouteServiceProvider.php
├── Repositories/
│   ├── EloquentRideRepository.php
│   └── EloquentRideOfferRepository.php
├── Services/                           # cohesive multi-step domain services
│   ├── RideStateMachine.php
│   ├── DispatchService.php
│   ├── FareLockService.php
│   └── RideAccountingService.php
├── StateMachine/
│   ├── RideStatus.php                  # enum
│   └── Transitions.php                 # allowed transitions map
├── Broadcasting/
│   ├── RideChannel.php
│   └── DriverChannel.php
├── routes/
│   ├── api_customer.php
│   ├── api_driver.php
│   └── channels.php
└── lang/
    └── (overrides if any)
```

### Conventions

- **Controllers** are thin. They (1) authorize, (2) validate via FormRequest, (3) call exactly one Action, (4) return a Resource. No business logic. No Eloquent queries beyond `findOrFail` via implicit route binding.
- **Actions** are immutable, injected by container, expose a single `execute(Dto): Dto` method, return a DTO (never a model directly). They are the unit of business behavior we test.
- **Services** orchestrate multi-step flows that don't fit a single command (e.g. `RideStateMachine`).
- **Repositories** are only used where we need a seam — heavy read paths and tests. Simple CRUD goes through Eloquent directly inside Actions.
- **DTOs** are constructed from arrays or FormRequests. We use a small `Dto` base or `spatie/laravel-data` (decision: lock to **`spatie/laravel-data`** for consistency with Filament resources).
- **Events** are emitted from Actions only. Listeners do side effects (broadcasting, notifications, accounting). This is how cross-module communication happens.
- **Jobs** are queued operations. They must be idempotent (use `unique` middleware + business idempotency keys).
- **Models** belong to one module and are never imported from another module. Cross-module reads happen through `Contracts\*Repository` interfaces.
- **Notifications** use Laravel's Notification system with the FCM channel from `kreait/laravel-firebase`.
- **Resources** are API output shaping; never leak DB columns directly. Use `whenLoaded()` to avoid N+1.

## 3.3 Cross-cutting infrastructure

### Idempotency
`App\Support\Idempotency\IdempotencyStore`:
- Middleware `EnsureIdempotency` on all `POST`/`PATCH` API routes that mutate state.
- Stores `(user_id, route, key) → {status, body_sha256}` in Redis with 24 h TTL.
- Replays the same response if the key is reused with the same body; rejects with `409` if body differs.

### Request ID / tracing
- `LogRequestId` middleware generates `X-Request-Id` if absent, attaches it to logs and Telemetry spans.
- Mobile clients echo it back; admin includes it in error reports.

### Errors
- `App\Exceptions\Handler` produces a single JSON envelope:
  ```json
  {
    "error": {
      "code": "ride.not_offerable",
      "message": "Human-readable, localized.",
      "details": { "...": "..." },
      "request_id": "01HXYZ..."
    }
  }
  ```
- Domain exceptions extend `App\Support\Exceptions\DomainException` and carry an HTTP status + machine code.

### Validation
- All input validated by FormRequest classes. Rules localized via `lang/<locale>/validation.php`.
- Coordinates validated through a custom `valid_lat_lng` rule.

### Localization
- `Localization` middleware reads `Accept-Language` and clamps to `{ka, en, ru}`.
- API resources call `__()` for any user-facing strings.

### Rate limiting
- Configured in `RouteServiceProvider` via named limiters: `auth.otp`, `auth.login`, `api.default`, `api.write`, `driver.location`, `support.create`.
- Specific limits in [05 API routes §rate limits](05-api-routes.md#rate-limits).

### Queues (Horizon)
- Three queues:
  - `realtime` — high priority: dispatch, broadcast retries, push.
  - `default` — most jobs.
  - `low` — analytics rollups, document hashing, archival.
- Horizon supervisor config provides separate worker pools sized accordingly. `realtime` workers do not autoscale below 4 in production.

### Broadcasting
- `BroadcastServiceProvider` aggregates channel files from each module's `routes/channels.php`.
- Reverb is the broker; in failover we point env to managed Pusher with no code change.
- Channels: see [07 §channels](07-realtime-ride-lifecycle.md#channels).

### Caching strategy
- `config(...)` heavy values (fare rules, surge, app_configs) wrapped in `Cache::remember()` with a tag invalidated on admin write. Tagged cache requires the Redis store.
- ETag/`Last-Modified` headers on read-mostly resources (`/api/customer/cities`, `/api/customer/promo_codes/available`).

### Map provider abstraction
`App\Modules\Geo\Contracts\MapProvider`:
- `routing(from, to): RouteResult` (distance_m, duration_s, polyline)
- `eta(from, to): seconds`
- `reverseGeocode(point): Address`
- `placeAutocomplete(query, near): Place[]`

Implementations: `GoogleMapsProvider`, `OsrmMapboxProvider`. Selected via `config('geo.provider')`.

### Payment gateway abstraction
`App\Modules\Payment\Contracts\PaymentGateway`:
- `authorize(amount, currency, methodToken, ride): GatewayResult`
- `capture(intentId): GatewayResult`
- `refund(intentId, amount): GatewayResult`
- `attachMethod(user, providerToken): PaymentMethod`

Implementations registered by string key in `config('payments.gateways')`.

## 3.4 Package inventory

| Purpose | Package |
|---|---|
| Auth (API) | `laravel/sanctum` |
| Social login | `laravel/socialite` + `socialiteproviders/apple` |
| Admin panel | `filament/filament` ^3 |
| Permissions | `spatie/laravel-permission` |
| Activity log | `spatie/laravel-activitylog` |
| Media (driver docs) | `spatie/laravel-medialibrary` (S3 disk) |
| DTOs | `spatie/laravel-data` |
| Phone normalization | `propaganistas/laravel-phone` |
| Localization helpers | `mcamara/laravel-localization` |
| Push (FCM) | `kreait/laravel-firebase` |
| Realtime | `laravel/reverb` |
| Queue dashboard | `laravel/horizon` |
| Telescope (non-prod) | `laravel/telescope` |
| Stripe | `stripe/stripe-php` |
| SMS | abstracted; initial driver = `twilio/sdk`, secondary = local GE provider HTTP client |
| Testing | `pestphp/pest`, `pestphp/pest-plugin-laravel` |
| Static analysis | `larastan/larastan` (level 8) |
| Code style | `laravel/pint` |
| API docs | `dedoc/scramble` (OpenAPI generated from FormRequests + Resources) |
| Healthchecks | `spatie/laravel-health` |

## 3.5 Config and secrets

- `.env` for local; production reads from AWS Secrets Manager / Vault via `vlucas/phpdotenv` overlay or systemd EnvironmentFile.
- **No** secrets in repo. `.env.example` lists every required key with safe placeholders.
- Sensitive runtime config goes via `config:cache` baked at deploy time.

## 3.6 Testing strategy

- **Pest** for everything, `Feature` tests per Action + per HTTP endpoint.
- Database: SQLite-in-memory only for fast unit tests; **MySQL via testcontainers** for any test touching spatial functions, generated columns, or `FOR UPDATE`. CI runs the MySQL suite.
- Coverage gate: 80% on Actions, 70% overall, enforced in CI.
- Contract tests against each external service (`stripe`, `fcm`, `twilio`, `googlemaps`) via recorded fixtures (`vcr`).
- Realtime: a `Reverb` integration test boots a real local broker on CI.

## 3.7 Coding standards & lint

- PHP 8.3 features encouraged: readonly classes for DTOs and value objects, enums for statuses, first-class callable syntax.
- `declare(strict_types=1);` on every file.
- Pint config extends Laravel preset with: ordered imports, no_unused_imports, single_quote.
- Larastan level 8 in CI on `app/Modules/**`.
- PSR-12.

## 3.8 What lives where — quick reference

| Concern | Path |
|---|---|
| Customer ride routes | `app/Modules/Riding/routes/api_customer.php` |
| Driver online toggle | `app/Modules/Driver/Actions/SetDriverOnline.php` |
| Surge calculation | `app/Modules/Pricing/Services/SurgeCalculator.php` |
| Nearby drivers query | `app/Modules/Geo/Services/NearbyDriverIndex.php` (Redis) |
| Stripe webhook handler | `app/Modules/Payment/Http/Controllers/Webhook/StripeWebhookController.php` |
| FCM channel binding | `app/Modules/Communication/Channels/FcmChannel.php` |
| Ride state machine | `app/Modules/Riding/Services/RideStateMachine.php` |
| Driver doc upload | `app/Modules/Driver/Actions/UploadDriverDocument.php` |
| SOS handling | `app/Modules/Support/Actions/RaiseSos.php` |
