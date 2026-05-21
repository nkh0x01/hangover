# 07 — Realtime & Ride Lifecycle

The heart of the platform. This document is binding: the state machine, the events, and the channel names cannot drift between backend and mobile without a coordinated migration.

## 7.1 Ride state machine

```
                 customer requests
                       │
                       ▼
                ┌─────────────┐
                │  REQUESTED  │ (fare locked, no driver yet)
                └──────┬──────┘
                       │ DispatchService starts
                       ▼
                ┌─────────────┐  no drivers within 60 s
                │  SEARCHING  │ ───────────────────────► NO_DRIVERS  (terminal)
                └──────┬──────┘
                       │ offer to closest driver
                       ▼
                ┌─────────────┐  reject / timeout (12 s)
                │   OFFERED   │ ─────────────► back to SEARCHING (next driver)
                └──────┬──────┘
                       │ driver accepts
                       ▼
                ┌─────────────┐  driver cancels  ┐
                │  ACCEPTED   │ ───────────────► │  customer / admin / driver
                └──────┬──────┘                  ▼  cancel paths all transition to
                       │ driver presses                CANCELLED  (terminal)
                       │ "on my way"               ▲
                       ▼                            │
                ┌──────────────────┐               │
                │ DRIVER_ARRIVING  │ ──────────────┤
                └──────┬───────────┘               │
                       │ within 30 m of pickup     │
                       ▼                            │
                ┌──────────────────┐               │
                │ DRIVER_ARRIVED   │ ──────────────┤
                └──────┬───────────┘               │
                       │ driver starts trip        │
                       ▼                            │
                ┌──────────────────┐               │
                │   IN_PROGRESS    │ ──────────────┘
                └──────┬───────────┘
                       │ driver completes
                       ▼
                ┌──────────────────┐
                │    COMPLETED     │ (terminal; payment + payout + rating window open)
                └──────────────────┘

                 catastrophic backend error → FAILED (terminal; no charge)
```

Allowed transitions are encoded in `App\Modules\Riding\StateMachine\Transitions`. Any out-of-machine update raises `InvalidRideTransitionException` and is logged to `ride_status_logs` with `to_status` unchanged.

### Status reference

| Status | Customer sees | Driver sees | Charge state |
|---|---|---|---|
| `requested` | "Looking for driver" | – | preauth pending |
| `searching` | "Looking for driver" (same UI) | – | preauth pending |
| `offered` | – (only after `accepted`) | "New ride: 12 s to respond" | preauth pending |
| `accepted` | "Driver is on the way" | "Heading to pickup" | preauth pending |
| `driver_arriving` | "Driver is 2 min away" | "Arriving" | preauth pending |
| `driver_arrived` | "Driver has arrived" | "Waiting for customer (free 3 min)" | waiting timer running |
| `in_progress` | live map + driver info | nav overlay | meter running |
| `completed` | summary + rating prompt | summary + earnings | captured |
| `cancelled` | reason + (maybe) fee | reason + (maybe) penalty | preauth voided or cancellation fee charged |
| `no_drivers` | "No drivers available" | – | preauth voided |
| `failed` | "Something went wrong" | – | preauth voided |

## 7.2 Dispatch algorithm

Triggered by `RideRequested` event. The `DispatchService` runs as a queued job on the `realtime` queue.

```
1. Verify ride is still in REQUESTED.
   Transition to SEARCHING. Persist + broadcast.

2. Compute candidate pool:
   - Redis GEOSEARCH drivers:online:<cityId>
     FROMLONLAT pickup.lng pickup.lat BYRADIUS 3 km ASC COUNT 30
   - For each driver:
       * Filter: drivers.status='approved', online=true, vehicle_id present, vehicle_type allowed by fare_rule
       * Filter: not already on an active ride
       * Filter: not in this ride's reject set (Redis ride:{id}:rejects)
   - Score:
       score = w1 * (1/distance_m)
             + w2 * rating_avg
             + w3 * acceptance_rate (driver_stats:{id}:accept_rate, 24 h window)
             - w4 * minutes_since_last_offer (avoid spamming top driver)
     w1=1.0, w2=0.2, w3=0.3, w4=0.05  (tuned later; lives in app_configs)

3. If candidate pool empty:
   - If elapsed < 60 s: enqueue OfferRideToNextDriver with delay 5 s (radius expands to 5 km after 20 s, 8 km after 40 s).
   - If elapsed >= 60 s: transition NO_DRIVERS, void preauth.

4. Top candidate is offered:
   - Insert ride_offers row (response=pending, expires_at = now+12 s).
   - SET in Redis ride:{id}:current_offer = driver_id with TTL 12 s.
   - Broadcast private-driver.{driverId}: { event: 'ride.offered', ride_summary, fare }.
   - Push notification (high-priority data + alert + PushKit on iOS).

5. Race:
   a) Driver POST /driver/offers/{ulid}/accept
      - In a transaction:
         * Lock the ride row FOR UPDATE.
         * Verify status=SEARCHING, current_offer matches.
         * Insert active_driver_lock generated col will enforce uniqueness;
           if another ride already took this driver → 409.
         * Transition ACCEPTED; set driver_id, vehicle_id, accepted_at.
         * Insert ride_status_log.
      - Broadcast private-ride.{rideId}: { event: 'ride.accepted', driver: {...} }.
      - Push to customer.
      - Cancel pending ExpireRideOffer job (or it no-ops on the next status check).

   b) Driver POST /driver/offers/{ulid}/reject
      - ride_offers.response='rejected', responded_at.
      - SADD ride:{id}:rejects driver_id.
      - Re-run step 2 immediately.

   c) Timeout (ExpireRideOffer fires at 12 s)
      - If ride still SEARCHING and current_offer unchanged:
         * ride_offers.response='timeout'.
         * SADD ride:{id}:rejects driver_id (treat as unresponsive).
         * Re-run step 2.

6. Once ACCEPTED, the per-ride realtime loop owns the ride until completion.
```

The whole loop targets p95 < 800 ms from `request` to the first `offered` broadcast.

## 7.3 Live location pipeline

### Ingestion

- Driver app posts `POST /driver/location` with `{lat, lng, heading, speed_kmh, accuracy_m, battery_pct, recorded_at}` at the cadence specified in [04 §realtime](04-flutter-app-structure.md#packagesrealtime).
- Server:
  1. Validates lat/lng plausibility (within bounding polygon of city; max speed 80 km/h for scooter type; accuracy < 60 m or down-weighted).
  2. **Inserts** into `live_locations` (canonical history) — async via `INSERT DELAYED`-equivalent: pushed onto a Redis stream `loc:ingest` and drained by a small worker batch-inserting every 500 ms. Keeps the API endpoint < 20 ms.
  3. **Updates Redis hot index** synchronously:
     - `GEOADD drivers:online:<cityId> lng lat driver:<id>`
     - `HSET driver:<id>:meta heading=… speed=… recorded_at=…`
     - `EXPIRE driver:<id>:meta 60`
  4. If driver is on an active ride:
     - Append to `ride:{rideId}:trace` (Redis list, capped 5000).
     - Broadcast `private-ride.{rideId}` event `driver.location` (throttled to ≤ 1 Hz with a rolling-window debounce in Redis).
  5. Geofence checks:
     - If `accepted` and within 200 m of pickup → suggest `driver_arriving` to driver UI (driver presses button, server validates within 200 m).
     - If `driver_arrived` and customer enters trip start zone (within 30 m, same heading) → enable Start button.

### Egress (customer subscriber)

- The customer's `RideTracking` page subscribes to `private-ride.{rideUlid}`.
- It receives `driver.location` events; the marker is interpolated client-side (smooth path between two points using time deltas).
- Map polyline (route preview) is fetched once at `accepted` from `MapProvider::routing(driver.pos, pickup)`; updated when `in_progress` to (pickup, dropoff). The map provider may return a static polyline; for ongoing drift, client snaps marker to its current GPS only.

### Trip trace finalization

- On `complete`:
  - The worker reads `ride:{id}:trace`, **decimates** with Ramer-Douglas-Peucker to ≤ 200 points, and inserts into `ride_route_points`.
  - Computes `distance_km` and `duration_seconds` from the decimated polyline + reported start/stop times (cross-checked against MapProvider routing as a sanity bound — distances > 1.4× routing distance flag a fraud row).
  - Clears `ride:{id}:trace`.

## 7.4 Channels

| Channel | Type | Subscribers | Events |
|---|---|---|---|
| `private-ride.{rideUlid}` | Private | Customer of ride + assigned driver | `ride.status_changed`, `driver.location`, `chat.message`, `eta.updated`, `ride.completed`, `ride.cancelled` |
| `private-driver.{driverUlid}` | Private | The driver themselves | `ride.offered`, `ride.offer_expired`, `ride.cancelled_by_customer`, `driver.suspended`, `payout.processed` |
| `private-customer.{customerUlid}` | Private | The customer themselves | `wallet.updated`, `promo.granted`, `account.suspended` |
| `presence-city.{cityId}.drivers` | Presence | Admin/dispatcher only | `driver.online`, `driver.offline`, `driver.location` (sampled to 0.5 Hz) |
| `presence-city.{cityId}.rides` | Presence | Admin/dispatcher only | `ride.created`, `ride.status_changed`, `ride.completed` |
| `private-support-ticket.{ticketUlid}` | Private | Ticket owner + assigned agent | `support.message`, `support.status_changed` |

Authorization for each channel is defined in the relevant module's `routes/channels.php` and uses Sanctum token auth via `Broadcast::channel(..., callback)`.

## 7.5 Wire protocol

Reverb/Pusher protocol. All payloads are JSON. Events use `dot.case` names. Each event carries a `v` integer for schema versioning.

### `ride.status_changed`
```
{
  "v": 1,
  "ride_ulid": "01HXYZ...",
  "from": "accepted",
  "to": "driver_arriving",
  "at": "2026-05-12T14:33:21.512Z",
  "actor": "driver"
}
```

### `driver.location`
```
{
  "v": 1,
  "ride_ulid": "01HXYZ...",      // omitted for presence-city stream
  "lat": 41.715,
  "lng": 44.827,
  "heading": 87,
  "speed_kmh": 18.5,
  "at": "2026-05-12T14:33:22.001Z"
}
```

### `chat.message`
```
{
  "v": 1,
  "ride_ulid": "01HXYZ...",
  "message_ulid": "01HXY...",
  "from": "driver",
  "body": "I am at the entrance",
  "type": "text",
  "sent_at": "2026-05-12T14:34:00.220Z"
}
```

### `ride.offered` (to driver)
```
{
  "v": 1,
  "ride_ulid": "01HXYZ...",
  "expires_at": "2026-05-12T14:33:33.000Z",
  "pickup": { "lat": 41.715, "lng": 44.823, "address": "Marjanishvili 4" },
  "dropoff": { "lat": 41.701, "lng": 44.797, "address": "Vake Park" },
  "distance_to_pickup_m": 320,
  "fare": { "amount": 7.50, "currency": "GEL" },
  "customer_rating_avg": 4.92
}
```

### `eta.updated`
```
{
  "v": 1,
  "ride_ulid": "01HXYZ...",
  "to": "pickup",       // or "dropoff"
  "seconds": 124
}
```

## 7.6 Reconnect & state reconciliation

When the mobile client reconnects to a channel:
1. It immediately calls `GET /<role>/rides/{ulid}` to get the canonical state.
2. It clears in-memory event buffers and seeds from the API response.
3. It resubscribes; from then on, it trusts the WS stream until next disconnect.

There is no replay buffer on the broker — clients always reconcile via REST after any disconnect lasting more than 2 s.

## 7.7 Battery & data optimization (driver app)

- Adaptive cadence already noted; coalesce multiple sensor readings client-side and only POST one summarized sample per cadence tick.
- WS payloads use the minimum field set per event.
- Background mode:
  - iOS: `UIBackgroundModes: location, voip, fetch`. PushKit wakes the app for new offers.
  - Android: foreground service with persistent notification while online; coarse network awareness lowers cadence on slow networks.
- The app refuses to go online if battery < 10% **and** not charging (configurable threshold).
- Telemetry: `driver.app.location.tick` event tagged with cadence band; we measure actual cadence in production and adjust the bands quarterly.

## 7.8 Failure modes & fallbacks

| Failure | Behavior |
|---|---|
| Reverb broker down | API returns 200s; mobile sees no WS events; we degrade ride-tracking to **2 s polling** of `GET /rides/{ulid}` and `GET /rides/{ulid}/messages?since=` |
| Redis down | API returns 503 on dispatch endpoints (we refuse to take new rides without the geo index); existing rides continue via DB; admin paged |
| MySQL primary down | API returns 503; read-only endpoints fall back to replica |
| Map provider down | `placeAutocomplete` returns the last known cache; fare estimate uses straight-line distance × city-average detour factor and flags `estimate.degraded=true` |
| FCM down | We retry through second push provider for critical events; UI surfaces are not blocked by push delivery — WS + polling cover it |
| SMS provider down | Fallback to secondary provider; if both down, we surface a degraded sign-in screen with an explanation |

## 7.9 Observability for ride flows

- A trace per ride spans from `RideRequested` to `ride.completed` / terminal. Correlated by `ride_ulid` and `X-Request-Id`.
- SLO metrics:
  - `dispatch.first_offer_latency_seconds` (histogram)
  - `dispatch.accept_rate_24h` (gauge per driver and global)
  - `ride.cancellation_rate_by_reason` (counter)
  - `driver.location_drop_rate` (counter — % of cadence ticks missed)
  - `ws.connection_age_seconds` (histogram)
- Alerts:
  - p95 `dispatch.first_offer_latency_seconds` > 1.5 s for 5 min → page on-call.
  - `ride.no_drivers` rate doubles week-over-week per city → page product.
  - WS broker availability < 99.5% over 30 min → page SRE.
