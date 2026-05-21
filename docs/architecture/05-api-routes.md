# 05 — API Route Planning

## 5.1 Conventions

- Base URLs:
  - Production: `https://api.hangover.app`
  - Staging: `https://api.staging.hangover.app`
  - Local: `http://localhost:8000`
- Versioning: URL prefix `/api/v1/`. New major version = `/api/v2/`. Same Laravel app for at least one full major-version overlap.
- Auth: `Authorization: Bearer <sanctum_token>` for all routes except those marked **public**.
- Content type: `application/json` request and response, UTF-8.
- Required client headers on every request:
  - `X-Device-Id` (UUID, persistent per install)
  - `X-App-Version` (`1.4.2`)
  - `X-Platform` (`ios|android`)
  - `Accept-Language` (`ka|en|ru`)
- Response envelope:
  - Success: `{ "data": ... }` (or `{"data": [...], "meta": {pagination...}}` for lists)
  - Error: `{ "error": { code, message, details, request_id } }`
- Timestamps: ISO-8601 UTC with millisecond precision, e.g. `2026-05-12T14:33:21.512Z`.
- IDs in payloads are always **ULID strings**, never DB integer ids.
- Pagination: cursor-based via `?cursor=<ulid>&limit=20`. `meta.next_cursor` is `null` when exhausted.

## 5.2 Route inventory

Grouped by audience. Each entry lists method, path, intent, owning module.

### 5.2.1 Public / unauthenticated

| Method | Path | Module | Intent |
|---|---|---|---|
| GET | `/api/v1/health` | – | Liveness probe |
| GET | `/api/v1/version` | Cms | Minimum supported app version per platform |
| GET | `/api/v1/config` | Cms | Public runtime config (map provider key fields, supported payment methods per city) |
| GET | `/api/v1/cities` | Geo | Active cities, with center + bounding polygon |
| GET | `/api/v1/cms/pages/{slug}` | Cms | Static page (terms, privacy, faq) — `?locale=ka` |
| POST | `/api/v1/auth/otp/request` | Identity | Send OTP to phone |
| POST | `/api/v1/auth/otp/verify` | Identity | Verify OTP → issue token (creates user on first-time) |
| POST | `/api/v1/auth/oauth/google` | Identity | Exchange Google ID token |
| POST | `/api/v1/auth/oauth/apple` | Identity | Exchange Apple identity token |
| POST | `/api/v1/auth/refresh` | Identity | Rotate Sanctum token |
| POST | `/api/v1/webhooks/stripe` | Payment | Stripe events (signed) |
| POST | `/api/v1/webhooks/sms/{provider}` | Communication | Delivery receipts |

### 5.2.2 Customer (`/api/v1/customer/*`) — Sanctum + ability `customer`

| Method | Path | Intent |
|---|---|---|
| GET | `/me` | Current user profile |
| PATCH | `/me` | Update profile (name, locale, avatar) |
| DELETE | `/me` | Request account deletion (queues redaction) |
| POST | `/me/avatar` | Multipart avatar upload |
| GET | `/me/devices` | List active devices |
| DELETE | `/me/devices/{id}` | Revoke device |
| POST | `/me/devices/fcm-token` | Register/refresh FCM token |
| GET | `/addresses` | List favorite addresses |
| POST | `/addresses` | Create address |
| PATCH | `/addresses/{id}` | Update |
| DELETE | `/addresses/{id}` | Delete |
| GET | `/places/autocomplete?q=&near=` | Place autocomplete (proxied to map provider) |
| POST | `/places/reverse-geocode` | Body: `{lat,lng}` |
| GET | `/drivers/nearby?lat=&lng=` | Live nearby driver positions (rate-limited; only points, no PII) |
| POST | `/rides/estimates` | Get fare estimate; returns `fare_estimate.ulid` |
| POST | `/rides` | Request a ride (idempotency key required) |
| GET | `/rides/active` | The customer's current open ride, if any |
| GET | `/rides/{ulid}` | Ride detail |
| PATCH | `/rides/{ulid}/cancel` | Cancel ride (reason in body) |
| POST | `/rides/{ulid}/messages` | Send chat message |
| GET | `/rides/{ulid}/messages?cursor=` | Chat history |
| POST | `/rides/{ulid}/rating` | Rate driver |
| POST | `/rides/{ulid}/sos` | Raise SOS event |
| GET | `/rides?cursor=` | Ride history |
| GET | `/wallet` | Wallet balance |
| GET | `/wallet/transactions?cursor=` | Transactions |
| POST | `/wallet/topup` | Top up via saved card / Apple/Google Pay |
| GET | `/payments/methods` | Saved payment methods |
| POST | `/payments/methods` | Tokenize new card (returns provider client secret) |
| DELETE | `/payments/methods/{id}` | Remove |
| GET | `/promos/available` | Promos the customer can use |
| POST | `/promos/redeem` | Pre-redeem a code into the wallet (if applicable) |
| GET | `/support/tickets?cursor=` | List tickets |
| POST | `/support/tickets` | Open ticket |
| GET | `/support/tickets/{ulid}` | Detail |
| POST | `/support/tickets/{ulid}/messages` | Reply |
| POST | `/auth/logout` | Revoke current device token |

### 5.2.3 Driver (`/api/v1/driver/*`) — Sanctum + ability `driver` (also requires `drivers.status = approved` for most routes)

| Method | Path | Intent |
|---|---|---|
| GET | `/me` | Driver profile (extends user) |
| PATCH | `/me` | Update profile |
| GET | `/onboarding/status` | What docs are missing / pending review |
| POST | `/onboarding/documents` | Upload document (multipart: file, doc_type) |
| GET | `/onboarding/documents` | List uploaded docs + statuses |
| GET | `/vehicles` | List my vehicles |
| POST | `/vehicles` | Add vehicle |
| PATCH | `/vehicles/{id}` | Update |
| POST | `/vehicles/{id}/photos` | Upload photos |
| POST | `/vehicles/{id}/activate` | Set current vehicle |
| POST | `/status/online` | Go online (lat,lng,vehicle_id) |
| POST | `/status/offline` | Go offline |
| POST | `/location` | Single GPS heartbeat (high frequency) |
| GET | `/offers/active` | The currently-offered ride, if any |
| POST | `/offers/{ulid}/accept` | Accept the offer |
| POST | `/offers/{ulid}/reject` | Reject |
| GET | `/rides/active` | Active ride for this driver |
| GET | `/rides/{ulid}` | Ride detail (driver view) |
| POST | `/rides/{ulid}/arriving` | Mark as driver_arriving |
| POST | `/rides/{ulid}/arrived` | Mark as driver_arrived (geofence check server-side) |
| POST | `/rides/{ulid}/start` | Start trip |
| POST | `/rides/{ulid}/complete` | Complete trip (final coords + waiting time) |
| PATCH | `/rides/{ulid}/cancel` | Cancel (reason) |
| POST | `/rides/{ulid}/messages` | Chat |
| POST | `/rides/{ulid}/rating` | Rate customer |
| POST | `/rides/{ulid}/sos` | SOS |
| GET | `/earnings/summary?period=day|week|month` | Stats |
| GET | `/earnings/transactions?cursor=` | Transaction list |
| GET | `/wallet` | Wallet balance |
| POST | `/wallet/withdraw` | Request payout |
| GET | `/wallet/payouts?cursor=` | Payout history |
| GET | `/heatmap?city_id=` | Demand heatmap (Phase 5+; behind feature flag) |
| GET | `/support/...` | Mirrors customer support endpoints |
| POST | `/auth/logout` | Revoke device |

### 5.2.4 Admin (`/api/v1/admin/*`) — Sanctum + role-based permissions

The admin **panel** itself uses Filament (session auth + Spatie permissions). The `/api/v1/admin/*` REST surface exists primarily for the dispatcher tools and any future external admin clients. Permissions enforced per route via Spatie middleware (`can:ride.cancel`, etc.).

| Method | Path | Required permission |
|---|---|---|
| GET | `/dashboard/metrics` | `dashboard.view` |
| GET | `/users?...filters` | `user.view` |
| GET | `/users/{ulid}` | `user.view` |
| PATCH | `/users/{ulid}/status` | `user.suspend` |
| GET | `/drivers?...filters` | `driver.view` |
| GET | `/drivers/{ulid}` | `driver.view` |
| POST | `/drivers/{ulid}/approve` | `driver.approve` |
| POST | `/drivers/{ulid}/reject` | `driver.approve` |
| POST | `/drivers/{ulid}/suspend` | `driver.suspend` |
| POST | `/documents/{id}/approve` | `driver.approve` |
| POST | `/documents/{id}/reject` | `driver.approve` |
| GET | `/rides?...filters` | `ride.view` |
| GET | `/rides/{ulid}` | `ride.view` |
| POST | `/rides/{ulid}/cancel` | `ride.cancel` |
| POST | `/rides/{ulid}/refund` | `refund.create` |
| POST | `/rides/{ulid}/dispatch-to/{driverUlid}` | `ride.dispatch` (dispatcher) |
| GET | `/live-map?city_id=` | `livemap.view` |
| GET | `/fare-rules` / POST / PATCH / DELETE | `pricing.manage` |
| GET | `/surge` / POST | `pricing.manage` |
| GET | `/zones` / POST / PATCH / DELETE | `pricing.manage` |
| GET | `/promo-codes` / POST / PATCH / DELETE | `promo.manage` |
| GET | `/support/tickets?...` | `support.view` |
| PATCH | `/support/tickets/{ulid}` | `support.respond` |
| POST | `/support/tickets/{ulid}/messages` | `support.respond` |
| GET | `/fraud-flags` / PATCH | `fraud.manage` |
| GET | `/sos-events` / PATCH | `sos.manage` |
| GET | `/payouts` / POST `/payouts/{id}/process` | `payout.manage` |
| GET | `/cms/pages` / POST / PATCH | `cms.manage` |
| GET | `/app-configs` / PATCH | `config.manage` |
| GET | `/audit-logs?cursor=` | `audit.view` |

### 5.2.5 Internal / system

| Method | Path | Auth | Intent |
|---|---|---|---|
| POST | `/api/v1/internal/dispatch/tick` | HMAC | Cron pulse to drive timeouts |
| POST | `/api/v1/internal/cache/invalidate` | HMAC | Hot-reload feature flags |

These are reachable only from the cluster; ingress denies them publicly.

## 5.3 Rate limits

Named limiters in `RouteServiceProvider`:

| Limiter | Applies to | Limit |
|---|---|---|
| `auth.otp` | `POST /auth/otp/request` | 3 / 60 s / IP **and** 5 / 60 min / phone |
| `auth.verify` | `POST /auth/otp/verify` | 6 / 10 min / phone |
| `auth.refresh` | `POST /auth/refresh` | 10 / 60 s / device |
| `api.default` | All authenticated reads | 120 / 60 s / user |
| `api.write` | Authenticated mutating | 60 / 60 s / user |
| `driver.location` | `POST /driver/location` | 120 / 60 s / driver (≈ 2 Hz) |
| `support.create` | `POST /support/tickets` | 5 / 24 h / user |
| `rides.create` | `POST /customer/rides` | 5 / 60 s / customer + 30 / day |
| `sos.create` | `POST .../sos` | 5 / hour / user (very high ceiling but bounded) |

Burst tokens stored in Redis. 429 responses include `Retry-After` and `X-RateLimit-Reset`.

## 5.4 Idempotency

All `POST`/`PATCH` mutating endpoints accept (and many **require**) `Idempotency-Key: <ulid>`. Required on:

- `POST /customer/rides`
- `POST /customer/rides/{ulid}/messages`
- `POST /customer/wallet/topup`
- `POST /customer/payments/methods`
- `POST /driver/offers/{ulid}/accept` / `reject`
- `POST /driver/rides/{ulid}/{arriving|arrived|start|complete}`
- `PATCH /*/rides/{ulid}/cancel`
- `POST /driver/wallet/withdraw`

If the same key is replayed with the same body, the cached response is returned. Different body → `409 Conflict`.

## 5.5 Errors — canonical codes

A non-exhaustive list of machine-readable codes the apps will switch on:

| Code | HTTP | Meaning |
|---|---|---|
| `auth.invalid_otp` | 422 | OTP wrong or expired |
| `auth.otp_throttled` | 429 | Cooldown active |
| `auth.invalid_token` | 401 | |
| `auth.token_expired` | 401 | Refresh required |
| `auth.device_revoked` | 401 | |
| `app.outdated` | 426 | Client below minimum supported version |
| `validation.failed` | 422 | Includes `details.fields` |
| `ride.no_drivers` | 503 | No drivers found within timeout |
| `ride.not_offerable` | 409 | Already accepted, expired, or cancelled |
| `ride.invalid_transition` | 409 | State-machine violation |
| `ride.duplicate_active` | 409 | Customer already has an active ride |
| `driver.not_approved` | 403 | Cannot go online |
| `driver.has_active_ride` | 409 | Cannot accept a new offer |
| `payment.declined` | 402 | Card declined; `details.provider_code` |
| `payment.unavailable` | 503 | Gateway down; retry |
| `promo.not_eligible` | 422 | |
| `promo.expired` | 410 | |
| `support.attachment_too_large` | 413 | |
| `idempotency.conflict` | 409 | Same key, different body |
| `sos.duplicate` | 409 | An open SOS already exists |
| `server.unavailable` | 503 | |

## 5.6 WebSocket events surfaced over the API

Although events live on WS, the API documents them too so client teams can stub them deterministically. See [07 §events](07-realtime-ride-lifecycle.md#wire-protocol).

## 5.7 OpenAPI

`dedoc/scramble` produces `openapi.json` from FormRequests + Resources. Published at `/api/v1/docs` (admin-protected on prod, public on staging). The mobile build pipeline runs `openapi-generator` against staging to refresh DTO stubs in `packages/data` (codegen committed, not generated at app build time).
