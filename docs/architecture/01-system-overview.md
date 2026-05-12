# 01 — System Overview

## 1.1 Product summary

A two-sided real-time marketplace for scooter rides in dense urban areas (initial market: Tbilisi, Georgia → expandable). Customers request a ride; the closest eligible scooter driver is matched, dispatched, tracked live, paid, and rated. The product is functionally similar to Uber/Bolt but **scaled down for scooters**: shorter average trips, denser supply, lower fares, battery/fuel awareness in the driver app.

## 1.2 Non-functional requirements

| Concern | Target |
|---|---|
| Concurrent active rides (Year 1) | 2,000 |
| Concurrent online drivers (Year 1) | 5,000 |
| Driver location update rate | 2–5 s when online, 1 s during active trip, 10–30 s when idle |
| Dispatch decision latency (p95) | < 800 ms from `request` to `offer` |
| API p95 latency (non-realtime) | < 250 ms |
| Mobile cold start | < 2.5 s |
| Uptime | 99.9% monthly |
| RPO / RTO | 15 min / 1 h |
| Data residency | EU region (Frankfurt) for GDPR; Georgia-specific PII unrestricted but encrypted at rest |

## 1.3 Component diagram (logical)

```
                       ┌──────────────────┐
                       │  Admin Panel     │  (Laravel + Filament, server-rendered)
                       │  admin.app.tld   │
                       └────────┬─────────┘
                                │  Sanctum session
                                ▼
┌────────────────┐      ┌────────────────────────────────────────────────┐
│ Flutter        │      │ Laravel 11 API (api.app.tld)                  │
│ Customer App   │◀────▶│ ─ HTTP REST (Sanctum bearer)                  │
│ (iOS/Android)  │      │ ─ Domain modules: Auth, Riding, Driver,       │
└────────────────┘      │   Pricing, Payment, Wallet, Promo, Support,   │
                        │   Notification, GeoIndex                      │
┌────────────────┐      │ ─ Event bus (Laravel events + queued listeners)│
│ Flutter        │◀────▶│ ─ Broadcasting → Reverb/Pusher                │
│ Driver App     │      └─────┬───────────────┬──────────────┬──────────┘
└────────────────┘            │               │              │
       ▲                      ▼               ▼              ▼
       │                ┌──────────┐    ┌──────────┐   ┌──────────────┐
       │                │ MySQL 8  │    │  Redis   │   │  Reverb /    │
       │                │ (primary │    │  (cache, │   │  Pusher WS   │
       │                │ +replica)│    │  queues, │   │  (presence + │
       │                └──────────┘    │  geoset, │   │  private chs)│
       │                                │  locks)  │   └──────────────┘
       │                                └──────────┘
       │  WSS (private + presence channels)              ▲
       └─────────────────────────────────────────────────┘
                                                         │
                                              ┌──────────┴──────────┐
                                              │ Workers (Horizon)   │
                                              │ – Dispatch          │
                                              │ – Notifications     │
                                              │ – Settlements       │
                                              │ – Document review   │
                                              └─────────────────────┘

External: FCM (push), Stripe, Google Maps/OSRM, Twilio/local SMS GW,
          S3-compatible object storage (driver docs).
```

## 1.4 Technology choices and rationale

| Layer | Choice | Why |
|---|---|---|
| Backend framework | **Laravel 11 (PHP 8.3)** | Required by brief; mature, batteries-included, Horizon + Reverb + Sanctum + Filament fit perfectly |
| Primary DB | **MySQL 8.0** | Required; spatial functions (`ST_*`) cover route/geo math; replicable |
| Cache / pub-sub / queues / geo index | **Redis 7** | One technology, four jobs — sorted sets for drivers nearby, streams for events, queues, locks |
| Realtime | **Laravel Reverb** (Pusher-compatible) | Self-hosted, free, drop-in Pusher protocol so we can swap to managed Pusher/Ably if scale demands |
| Mobile | **Flutter (stable)** | Required; single codebase, mature maps + WS plugins |
| Push | **Firebase Cloud Messaging** | Required; iOS + Android; APNs delivered via FCM |
| Admin panel | **Filament 3 on Laravel** | Required option; ships with RBAC, resource CRUD, dashboards |
| Auth | **Laravel Sanctum** (mobile bearer tokens) + Laravel Socialite (Google/Apple) | Brief allowed JWT or Sanctum; Sanctum is simpler, ties cleanly to admin + API |
| Maps | **Google Maps SDK** primary, **Mapbox/OSRM** fallback via abstraction layer | Brief explicitly allows either; we abstract behind a `MapProvider` interface |
| Payments | **Stripe** primary, gateway-agnostic abstraction for local GE PSPs (BoG, TBC Pay) | Brief requires future readiness |
| Object storage | **S3-compatible** (AWS S3 or Cloudflare R2) | Driver documents + profile photos |
| Observability | OpenTelemetry → Grafana Cloud (or self-hosted Tempo+Loki+Mimir) | One stack for logs, metrics, traces |

## 1.5 Module boundaries (Domain-Driven slicing)

The Laravel codebase will be split into **bounded modules** (not microservices — a modular monolith):

1. **Identity** — users, sessions, phone OTP, social login, RBAC.
2. **Driver** — driver profile, vehicles, documents, approval workflow, online/offline.
3. **Geo** — live locations, geofencing, zone definitions, nearby-driver search.
4. **Pricing** — fare rules, surge, promo evaluation, fare estimate.
5. **Riding** — the ride entity and its state machine; dispatch; offers; status logs.
6. **Payment** — payment intents, payment methods, settlement, refunds.
7. **Wallet** — driver + customer wallet balance, transactions, withdrawals.
8. **Promotion** — promo codes, campaigns, eligibility rules.
9. **Rating** — ratings + reviews on both sides.
10. **Communication** — in-app chat, masked-number calls (Twilio Proxy or local equivalent), push, SMS, email.
11. **Support** — tickets, fraud flags, SOS events, audit log.
12. **CMS** — pages, FAQs, in-app banners, app config (feature flags).

Each module owns its DB tables, services, events, routes, and Filament resources. Cross-module collaboration is via **events** (preferred) or **published service contracts** in `App\Modules\<Module>\Contracts`. No direct Eloquent reach-across.

## 1.6 Realtime architecture summary

- **Broadcasting driver:** Reverb (Pusher protocol) over `wss://ws.app.tld`.
- **Driver location ingestion:** HTTP POST `/api/driver/location` accepted at 1–5 Hz; for high-density operations we will add a WebSocket inbound channel in Phase 3.
- **Live nearby query:** Redis `GEOADD drivers:online:<city>` + `GEOSEARCH ... BYRADIUS`. TTL per entry = 30 s; refreshed every heartbeat.
- **Customer subscriptions:** `private-ride.{rideId}` for status updates; `private-driver.{driverId}` for the driver's own events; `presence-city.{cityId}.drivers` for admin live-map.
- Details in [07 Realtime & ride lifecycle](07-realtime-ride-lifecycle.md).

## 1.7 Security overview

- TLS 1.2+ everywhere; HSTS on web surfaces.
- Mobile certificate pinning (SPKI pins for the API + WS hosts).
- Sanctum personal access tokens, **device-bound** (one token per (user, device_id)).
- Rate limiting per route group (see [05 API routes](05-api-routes.md) §rate limits).
- Driver documents stored encrypted server-side (S3 SSE-KMS) and **never** exposed via public URLs — only signed, short-lived URLs to admin reviewers.
- Sensitive PII columns (`id_number`, `bank_account`) encrypted with Laravel's `encrypted` cast.
- Activity log (Spatie `activitylog`) on all admin mutations and on every ride status transition.
- See [06 Authentication flow](06-authentication-flow.md) for auth specifics.

## 1.8 What is explicitly out of scope (v1)

- Driver-to-driver transfers.
- Multi-stop rides.
- Scheduled / pre-booked rides (designed for, not built).
- Corporate/B2B billing portal (designed for, not built).
- Heatmaps for drivers (data pipeline reserved; UI hidden behind feature flag).
- Vehicle telematics (designed-for via `vehicles.telemetry_*` columns; integration deferred).
