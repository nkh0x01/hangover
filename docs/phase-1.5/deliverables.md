# Phase 1.5 — Deliverables

## TL;DR

The first **fully working end-to-end ride flow** is live on
`claude/scooter-platform-architecture-Wvmeu`. A customer can request a
ride, a nearby driver receives an offer, accepts it, drives there,
starts the trip, and completes it — with the admin panel monitoring
every active ride. The state machine is locked by a database-level
unique invariant so two drivers cannot claim the same ride no matter
how aggressive the race.

## 1. Working end-to-end ride flow

```
   customer                       backend                      driver
   --------                       -------                      ------
1. POST /customer/rides/estimates
2. POST /customer/rides       ───► status=requested
                                  DispatchRide queued
                                  status=searching
3.                                Redis GEOSEARCH candidates
4.                                pick best → status=offered
                                  RideOffer row + RideOffered event
5.                                                           private-driver
                                                             receives ride.offered
6.                                                           POST /driver/offers/{id}/accept
                                  Row-lock + active_driver_lock
                                  status=accepted (only one wins)
                                  RideStatusChanged broadcast
7. private-ride receives           POST /driver/rides/{id}/arriving
   ride.status_changed             POST /driver/rides/{id}/arrived
                                  POST /driver/rides/{id}/start
                                  POST /driver/rides/{id}/complete
8. RideTrackingPage walks
   through each phase from
   one Riverpod controller
```

## 2. Dispatch overview

- **Entrypoint:** `DispatchRide` job (queued on `realtime`) fires the
  first tick after the customer's POST returns.
- **Pass:** `DispatchService::dispatchTick(Ride)` runs one round:
  1. Transition `requested → searching` if needed.
  2. Ask `DriverCandidateResolver` for the best candidates inside an
     adaptive radius (3 km → 5 km after 20 s → 8 km after 40 s).
  3. Candidates come from Redis `GEOSEARCH drivers:online:{city}`
     filtered against:
     - `drivers.status='approved' AND online=true AND current_vehicle_id IS NOT NULL`
     - drivers without any in-flight ride (sub-query)
     - drivers blacklisted for this ride (Redis `ride:{id}:rejects`)
     - stale GPS drivers (Redis index TTL = 60 s)
  4. Offer to candidate, persist `ride_offers` row, transition to
     `offered`, set Redis `ride:{id}:current_offer` with 12 s TTL,
     broadcast `RideOffered`, schedule `ExpireRideOffer` delayed job.
- **On accept:** `AcceptRideOffer` runs in a transaction with
  `SELECT … FOR UPDATE`. The UPDATE writes `driver_id`, which can
  collide with `active_driver_lock` if the driver already has another
  active ride — caught and re-thrown as `RideNotOfferableException`.
- **On reject:** `RejectRideOffer` flips the offer to `rejected`,
  transitions ride back to `searching`, blacklists the driver, queues
  `OfferRideToNextDriver`.
- **On timeout:** `ExpireRideOffer` job (delayed to expires_at) marks
  the offer `timeout` and re-enters the loop.
- **No candidates:** retry every 5 s with a wider radius until
  `realtime.offer.search_timeout_seconds` (60 s), then terminal
  `no_drivers`.

Tunables (all in `config/realtime.php`):

```php
'offer.expiry_seconds'           => 12,
'offer.initial_radius_km'        => 3,
'offer.max_radius_km'            => 8,
'offer.search_timeout_seconds'   => 60,
```

## 3. Realtime architecture summary

- Driver heartbeat → POST `/driver/location` → `IngestLocationHeartbeat`
  - Plausibility-checks the sample (speed cap from `geo.plausibility`).
  - Updates Redis hot index `drivers:online:{city}` (TTL 60 s) and the
    `driver:{id}:meta` hash.
  - Persists a `live_locations` row.
  - If the driver is on an active ride, broadcasts
    `DriverLocationUpdated` on `private-ride.{ulid}`.
- Customer subscribes to `private-ride.{ulid}` for `ride.status_changed`
  and `driver.location`.
- Driver subscribes to `private-driver.{ulid}` for `ride.offered`.
- Today the mobile clients use a **polling fallback** (`RideEventStream`,
  2 s tick) so the UI never freezes even if the broker drops a frame.
  The same Stream API will switch to true WS subscription once the
  `RealtimeClient` concrete implementation lands in Phase 2 — no UI
  changes required.

## 4. API list

### Customer (`/api/v1/customer/*` — `ability:customer`)

| Method | Path | Purpose |
|---|---|---|
| POST  | `/rides/estimates`               | Lock a fare quote (returns `fare_estimate.id`) |
| POST  | `/rides`                         | Create a ride at status=requested (idempotency key) |
| GET   | `/rides/active`                  | Currently-open ride or `null` |
| GET   | `/rides`                         | Ride history (latest 50) |
| GET   | `/rides/{ulid}`                  | Ride detail |
| PATCH | `/rides/{ulid}/cancel`           | Cancel — reason required |
| GET   | `/drivers/nearby`                | Position-only list of nearby drivers |

### Driver (`/api/v1/driver/*` — `ability:driver`)

| Method | Path | Purpose |
|---|---|---|
| POST  | `/status/online`                 | Go online (lat/lng/vehicle_id) |
| POST  | `/status/offline`                | Go offline |
| POST  | `/location`                      | GPS heartbeat (rate-limited per limiter `driver.location`) |
| GET   | `/rides/active`                  | Driver's currently-assigned ride |
| GET   | `/rides/{ulid}`                  | Ride detail (driver view) |
| POST  | `/offers/{ulid}/accept`          | Accept the offer (concurrency-safe) |
| POST  | `/offers/{ulid}/reject`          | Reject — kicks dispatch to next candidate |
| POST  | `/rides/{ulid}/arriving`         | Move to driver_arriving |
| POST  | `/rides/{ulid}/arrived`          | Move to driver_arrived |
| POST  | `/rides/{ulid}/start`            | Move to in_progress |
| POST  | `/rides/{ulid}/complete`         | Move to completed (writes final amount) |
| PATCH | `/rides/{ulid}/cancel`           | Driver cancel |

### Admin

- Filament dashboard widgets: `ActiveRidesWidget`, `OnlineDriversWidget`.
- `Operations → Active rides` page with 5 s polling table.

## 5. Mobile screen list

### Customer

1. **Splash** — token check + initial route.
2. **PhonePage** — phone OTP request (Georgian-first locale strings).
3. **OtpPage** — verify OTP + token persist.
4. **HomePage** — map, nearby driver pins (polls every 10 s),
   destination card.
5. **DestinationPage** — tap-to-pick destination + reverse-geocode.
6. **FareEstimatePage** — live fare card, cash/card segmented control,
   "Request ride" button.
7. **RideTrackingPage** — single screen that morphs through every
   phase (searching, driver-assigned, driver-arrived, in-progress,
   completed, cancelled, no_drivers).

### Driver

1. **Splash** — token + role check.
2. **PhonePage** + **OtpPage** — driver_signup purpose.
3. **HomePage** — map, online/offline toggle bar, heartbeat + active
   ride polling once online.
4. **IncomingOfferSheet** — full-screen overlay with countdown bound
   to `expires_at`, accept / reject buttons.
5. **ActiveRideSheet** — phase-aware buttons (on my way / arrived /
   start trip / complete trip).
6. **Ride completed** — earnings summary, dismiss action.

## 6. Testing overview

Pest, DB-backed against MySQL (CI provisions a real `mysql:8.0`
service container so the spatial columns and generated columns
actually run).

| Test | Coverage |
|---|---|
| `tests/Feature/Pricing/FareEstimateServiceTest.php` | Haversine distance, minimum-fare clamp, ulid persistence |
| `tests/Feature/Riding/ConcurrentAcceptTest.php` | **The cardinal correctness test** — two drivers race; only one wins. Plus re-accept rejection and expired-offer rejection. |
| `tests/Feature/Riding/RideLifecycleTest.php` | Happy path offered → accepted → driver_arriving → driver_arrived → in_progress → completed. Customer cancel path. Illegal-transition guard. |
| `tests/Feature/Riding/DuplicateActiveRideTest.php` | `active_customer_lock` partial unique index exercised end-to-end through CreateRideRequest. |
| `tests/Unit/RideTransitionsTest.php` (pre-existing) | Pure-Dart-equivalent transition map |
| `tests/Feature/HealthCheckTest.php`, `tests/Unit/MoneyTest.php` | Pre-existing smoke tests |

CI workflow `backend.yml` runs pint + phpstan + the full Pest suite
against MySQL + Redis on every push to a feature branch.

## 7. Known limitations before Phase 2

These are deliberate, scoped to the next phase, and documented so
nothing gets lost:

1. **Realtime WS not yet plumbed end-to-end** — the events broadcast
   from the server, but the mobile clients use a 2 s polling fallback.
   `RealtimeClient.connect()` is an abstract interface; Phase 2 wires a
   concrete `pusher_channels_flutter`-backed impl. The UI doesn't
   change because `RideEventStream` is the seam.
2. **Map provider is map-widget-only** — `GoogleMapsProvider.route /
   eta / reverseGeocode / placeAutocomplete` all return stubs. Phase 2
   wires Google Routes + Places API. The customer's destination
   picker is therefore tap-to-pick today, not search-as-you-type.
3. **No real payments yet** — `payment_method=cash` is the only fully
   working method; Stripe authorize/capture lands in Phase 3 per the
   roadmap. The `payment` column is recorded; just no charge.
4. **No driver document upload UI** — `Driver` records can be created
   via the seeder or via Filament for QA; the driver-onboarding upload
   flow is Phase 1 part 2.
5. **Fare estimate uses Haversine × 1.35 detour** — close enough for
   Tbilisi MVP; will swap to MapProvider routing in Phase 2.
6. **Surge always 1.0** — the column, table, and config knob exist;
   the surge calculator is Phase 2.
7. **Customer-side cancellation fee** — schema column exists; not yet
   enforced. Phase 2 ride accounting service will charge it on
   late cancels.
8. **No place autocomplete / no Georgian street geocoding** — Phase 2.
9. **Push notifications not yet wired** — FCM token is collected at
   OTP-verify time but no notification jobs are dispatched. WS covers
   the foreground case; Phase 2 will dispatch FCM for backgrounded
   apps.
10. **No driver chat with customer** — DB tables + channels exist;
    UI is Phase 3.
11. **No SOS / rating UI yet** — actions and tables exist on the
    backend, screens land in Phase 4.

## 8. How to demo locally

```bash
make up
make install       # composer install + migrate + seed (includes
                   # the fare rules seeder)
make logs          # in a second terminal

# Pretend to be a driver (Tbilisi center):
TOKEN=$(curl -sX POST http://localhost:8000/api/v1/auth/otp/request \
  -d '{"phone":"+995555111111","purpose":"driver_signup"}' \
  -H 'Content-Type: application/json' \
  | jq -r .data)
# Tail the sms log to grab the code:
tail backend/storage/logs/sms-$(date +%F).log

# Then verify, accept docs via admin, set the driver approved + online,
# bring up the driver app, and run the customer flow from a phone.
```

The detailed runbook lives in `docs/phase-0/deliverables.md` §7
(verifying the install). Phase 2 will add a `make demo-seed` target
that builds a fully approved driver, vehicle, online entry, and a
seeded ride for a one-shot demo.

## 9. Recommended next phase

Per `docs/architecture/10-development-roadmap.md` §Phase 2:

1. Wire `RealtimeClient` concretely with `pusher_channels_flutter`
   pointing at Reverb. Replace polling fallback in `RideEventStream`.
2. Concrete `GoogleMapsProvider.route / placeAutocomplete /
   reverseGeocode` — replace tap-to-pick with text-search-first UX.
3. Surge calculator + admin board (Phase 2 deliverable in the
   architecture doc).
4. Driver onboarding upload flow (documents UI + admin review).
5. Customer rating + driver rating UI.
