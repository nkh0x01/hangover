# Admin Monitoring Guide

> Phase 2.2 deliverable. Where ops + engineering look during pilot,
> what they expect to see, and what each anomaly means.
>
> Audience: Ops on-call, SRE on-call, Eng on-call. Read once before
> your first shift, refer to during.

## Where to look

| Surface                                     | URL / path                                 | Purpose                                  |
|---------------------------------------------|--------------------------------------------|-------------------------------------------|
| **Pilot dashboard** (Filament)              | `/admin → Operations → Pilot dashboard`    | KPIs: throughput, quality, supply, flags  |
| **Live rides** (Filament)                   | `/admin → Operations → Active rides`       | Per-ride drill-down, force-cancel         |
| **Drivers** (Filament)                      | `/admin → Drivers`                         | Online count, document status, strikes    |
| **Rides** (Filament)                        | `/admin → Rides`                           | All rides; filter by `is_test_ride`       |
| **Support inbox** (Filament)                | `/admin → Support → Incidents`             | New tickets, by severity                  |
| **Horizon**                                 | `/horizon`                                  | Queue depth, failed jobs, throughput       |
| **Telescope**                               | `/telescope` (dev/staging only)             | Recent requests, slow queries              |
| **Sentry**                                  | `sentry.io/hangover-mobility`               | Crashes, perf, breadcrumbs                |
| **Reverb metrics**                          | `realtime.hangover.app/metrics`             | WS connections, message rate              |
| **Logs**                                    | `storage/logs/{dispatch,realtime,push,security,payment}.log` | Per-channel daily rotated  |

## Pilot dashboard — what each number means

### Throughput

- **Rides today** — count of `is_test_ride = false` rides created
  since `00:00` local. Production traffic only.
- **Completed** — `status = completed` since `00:00`.
- **Active** — currently in any non-terminal state. Surge alert if
  > 30 concurrent during week 1 pilot.

### Quality

- **Cancel rate** = cancelled / total. Pilot target < 20%. Above
  threshold turns the stat red.
- **No-drivers rate** = `no_drivers` / total. Pilot target < 5%.
  Above threshold turns yellow; above 10% paging-worthy.
- **Avg pickup time** = `arrived_at − accepted_at` averaged across
  completed rides. Pilot target < 6 min in Tbilisi, < 4 min in
  Batumi.

### Supply

- **Online drivers** — count where `Driver.status = online`. Floor:
  3 per active city. Below floor turns red and pages.

## Live rides — drill-down workflow

Each row in the live monitor is one in-flight ride. The columns
to read at a glance:

| Column        | Healthy                                    | Watch out                                          |
|---------------|---------------------------------------------|-----------------------------------------------------|
| Status        | Progresses every 30-120 s                   | Stuck on `offered` > 60 s — dispatch bug?           |
| Customer      | Matches a known cohort phone or real number | Phone outside the pilot footprint — geo-mismatch    |
| Driver        | One of your active drivers                 | Anonymous or "(none)" longer than 30 s              |
| Pickup        | Resolves to a real address                 | Lat/lng only — geocoder failure                     |
| Quoted amount | Within the fare matrix                     | < base fare (bug) or surge > 1.5 (cap bypassed)     |

Click into a row to see the `RideStatusLog`. Each state should have
a sub-2-second gap to the next under normal load.

## Horizon — queue health

Pilot uses these named queues:

| Queue        | Consumers                  | Typical depth | Alert threshold                         |
|--------------|----------------------------|----------------|------------------------------------------|
| `default`    | most jobs                  | < 10           | > 100 sustained 5 min                    |
| `realtime`   | `DispatchRide`, `OfferRideToNextDriver`, `ExpireRideOffer`, `SendOfferPush` | < 5 | > 30 sustained 1 min |
| `payments`   | settlement, payouts        | < 5            | > 50                                      |
| `notifications` | non-realtime push, SMS  | < 20           | > 200                                     |

Failed jobs page: review the top of the list. If `SendOfferPush`
keeps failing for the same driver, their FCM token is dead — the
listener should have auto-purged it; if it didn't, that's a bug
to file.

## Sentry — what to triage now vs later

- **Triage now** (any of):
  - A new fingerprint with > 5 occurrences in the last hour.
  - Any unhandled `DomainException` (these are supposed to be
    converted to client errors at the boundary).
  - Any error in `Riding\Actions\*` — the ride lifecycle is sacred.
  - Any `OutOfMemoryError` / `Exception in Connection::*` from the
    DB layer.
- **Triage later** (during business hours):
  - Single-occurrence errors with familiar fingerprints.
  - 4xx from the mobile clients (usually validation that we want to
    tighten copy on).
  - Slow transactions > 2 s on rare endpoints.

## Reverb (WS broker) — what "normal" looks like

- Active connections ≈ #online drivers + #active customers.
- Message rate proportional to active-ride count × 5 (heartbeats +
  status updates).
- Sudden drop in connections → broker restart? Network blip?
- Sudden spike in connections → driver phones reconnecting after a
  hiccup; if it doesn't settle in 60 s, check the broker logs.

## Logs — what to grep for

```
storage/logs/dispatch.log
  - "No candidates" sequences > 3 in a row → supply gap.
  - "Re-queue: try again" loops > 5 → widen radius isn't helping.

storage/logs/realtime.log
  - WS auth failures → check the channels.php authorisation logic.

storage/logs/push.log
  - "FCM send failed" with errorCode UNREGISTERED → token rot,
    expected once per device per cycle.
  - Bursts of "Skipping stale offer push" → workers backed up.

storage/logs/security.log
  - OTP throttle hits → expected occasionally; bursts from one
    phone = abuse attempt.
  - 401 with valid token → device-binding mismatch, check the
    EnsureDeviceBound middleware.
```

## Alerting (suggested)

In Phase 2.3 we wire these to PagerDuty + Slack:

| Trigger                                                   | Page | Channel       |
|-----------------------------------------------------------|------|---------------|
| API 5xx > 5/min sustained 1 min                           | Yes  | #pilot-ops    |
| Reverb connection count = 0 for 30 s                      | Yes  | #pilot-ops    |
| Online drivers < pilot floor for 5 min                    | Yes  | #pilot-supply |
| Cancel rate > 25% rolling-hour                            | Yes  | #pilot-ops    |
| No-drivers count > 10 in any 1-hour window                | Yes  | #pilot-supply |
| Any Sentry "P0 incident" tag                              | Yes  | #pilot-ops    |
| `realtime` queue depth > 50 sustained 2 min               | Yes  | #pilot-eng    |
| MySQL connection pool exhaustion                          | Yes  | #pilot-eng    |
| Redis OOM                                                 | Yes  | #pilot-eng    |
| Disk free < 10% on any app server                         | Yes  | #pilot-eng    |
| Cron job missed > 15 min                                  | No   | #pilot-eng    |

Initial pilot weeks use Slack + manual paging via the on-call rota
sheet until PagerDuty integration is signed off.

## Manual ops actions

Listed for muscle-memory. All accessible via Filament with the
required permission.

| Action                          | Where                                        | Required permission              |
|---------------------------------|----------------------------------------------|-----------------------------------|
| Force-cancel a ride             | `/admin → Rides → {ride} → Force cancel`     | `rides.force_cancel`             |
| Issue refund                    | `/admin → Rides → {ride} → Issue refund`     | `incident.refund.issue`          |
| Suspend driver                  | `/admin → Drivers → {driver} → Suspend`      | `drivers.suspend`                |
| Reset OTP                       | `/admin → Users → {user} → Reset OTP`        | `users.reset_otp`                |
| Toggle pilot enabled            | `.env` → `PILOT_ENABLED=true` + redeploy     | shell on app server              |
| Add test phone                  | `.env` → `PILOT_TEST_PHONES=...` + redeploy  | shell on app server              |
| Take API into maintenance       | `php artisan down --secret=<token>`          | shell on app server              |
| Roll back to previous deploy    | CI/CD `deploy:rollback` action               | shell on CI                      |

## Shift handover

End-of-shift the outgoing on-call writes one paragraph in the
ops chat covering:
1. Incidents opened / closed during the shift.
2. Notable anomalies even if they didn't trigger an alert.
3. Open follow-ups for the incoming on-call.
4. Anything happening at handover (e.g. an in-flight P0).
