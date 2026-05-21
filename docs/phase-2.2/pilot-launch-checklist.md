# Hangover — Pilot Launch Checklist

> Phase 2.2 master go/no-go. Run top-to-bottom **once** in staging then
> again in production within 72 h of the public start. Every checkbox
> must be ticked AND owned (initials + date). Unticked items at T-0
> auto-postpone the pilot — see `launch-blocker-criteria.md` for the
> hard-stop rules.

City scope: **Tbilisi** (primary), **Batumi** (secondary).
Pilot cohort label: `tbilisi-w1` / `batumi-w1`.

## T-14 days — infrastructure

- [ ] **Backend** (`api.hangover.app`) deployed, healthchecks green
  for 24 h. Owner: SRE.
- [ ] **Reverb broker** (`realtime.hangover.app`) on the prod plan,
  TLS valid > 60 days, monitored. Owner: SRE.
- [ ] **MySQL** prod has the spatial index migration applied
  (`SHOW CREATE TABLE rides;` shows `pickup_location`, `dropoff_location`,
  `active_driver_lock`, `active_customer_lock`).
- [ ] **Redis** prod reachable from the API and from the workers.
  `redis-cli ping` returns `PONG` from each.
- [ ] **Horizon supervisors** running with the `realtime` queue
  consumer enabled. `php artisan horizon:status` is `running`.
- [ ] **Sentry** project receiving both backend + mobile events.
  Verified by deliberately throwing a test exception in staging.
- [ ] **Firebase** project created, `google-services.json` /
  `GoogleService-Info.plist` baked into each app flavor.
- [ ] **APNs auth key** uploaded to Firebase iOS app.
- [ ] **Twilio (or local SMS)** credentials live; OTP delivery <
  10 s p95. Owner: Comms.

## T-10 days — apps

- [ ] **Customer app v0.1.0** signed release built and installable on
  the QA device matrix (see `docs/phase-2.1/device-qa-scenarios.md`).
- [ ] **Driver app v0.1.0** signed release built + installed on at
  least 8 driver phones via `adb install` or TestFlight.
- [ ] **Locale**: Georgian + English copy reviewed by a native speaker
  for at least the auth + ride flows. Owner: Product.
- [ ] **Firebase remote config** placeholders set (`map_provider`,
  `realtime_driver`, `pilot_enabled`). Owner: SRE.
- [ ] **Crash logging**: `CrashReporter.bootstrap` is called in both
  apps' `bootstrap.dart`. Verified by triggering a Dart exception in
  a staging build and seeing it in Sentry < 60 s.
- [ ] **Permission UX**: pre-prompt screens cover location +
  notifications before the OS prompt is fired.

## T-7 days — supply

- [ ] **20 candidate drivers** identified and contacted. Target mix:
  - ≥ 5 with 2+ years of taxi-app experience (Bolt / Yandex).
  - ≥ 5 first-time platform drivers.
  - ≥ 2 women drivers.
  - ≥ 2 fluent English speakers (for English-speaking riders).
- [ ] **Documents collected and verified** for each (see
  `driver-onboarding-guide.md`):
  - Government ID
  - Driver license
  - Vehicle registration + technical inspection
  - Insurance
  - Background check (no DUI / no violent offence within 5 years)
- [ ] **Vehicles registered** in the admin panel with photos.
- [ ] **In-person training session** scheduled for each batch
  (see `driver-training-guide.md`).
- [ ] **First test ride per driver** completed end-to-end with an Ops
  shadow rider — tagged `is_test_ride = true`.

## T-5 days — pricing + policy

- [ ] **Fare matrix** published for both cities. Owner: Finance.
  Tbilisi: 2.50 GEL base + 0.95 GEL/km + 0.20 GEL/min. Batumi: 2.50 + 0.85 + 0.20.
- [ ] **Surge multiplier cap** = 1.5 during pilot. Hardcoded via
  `pricing.surge_max_multiplier`.
- [ ] **Cancellation policy** approved by Legal and surfaced in the
  customer app (Help → Cancellations).
- [ ] **Refund SOP** signed off by Finance + Support (see
  `cancellation-refund-rules.md`).
- [ ] **Driver commission** = 15% during pilot. Verified in
  `config/pricing.php`.

## T-3 days — support

- [ ] **Support inbox** monitored 07:00–23:00 local. Twilio number +
  ZenDesk (or simple Slack channel) configured.
- [ ] **Two on-call ops engineers** rota'd across the first 7 days
  with phones on loud. SLA: respond < 5 min to any P0 incident.
- [ ] **Incident playbook** (`support-workflow.md`) read and signed by
  every on-call.
- [ ] **Customer FAQ** published to `hangover.app/help` and linked
  from the in-app Help button.
- [ ] **Refund-issuance permission** granted in Filament to the
  Support role.

## T-1 day — final dress rehearsal

- [ ] **Smoke ride**: one ops customer + one ops driver complete the
  full happy-path lifecycle in production. Sentry shows zero new
  errors.
- [ ] **Push notification round-trip**: a deliberate offer reaches
  the driver phone within 3 s of dispatch.
- [ ] **Live monitor** (`/admin → Operations → Active rides`) shows
  the smoke ride in real time.
- [ ] **Pilot dashboard** (`/admin → Operations → Pilot dashboard`)
  shows the correct stats (1 test ride, 0 cancellations, etc.).
- [ ] **Daily-monitoring cron** wired and emitting Slack messages.
- [ ] **PILOT_ENABLED=true** flipped in prod `.env` AFTER smoke ride.
- [ ] **PILOT_COHORT** set to `tbilisi-w1`.
- [ ] **PILOT_TEST_PHONES** populated with the ops + driver-tester
  phone numbers.
- [ ] **Rollback plan** rehearsed: how to flip `PILOT_ENABLED=false`,
  how to take the API into maintenance mode, how to message active
  customers and drivers. Owner: SRE.

## T-0 — launch day

- [ ] **0700**: SRE confirms healthchecks across API + broker + queue
  workers + admin.
- [ ] **0800**: First 5 drivers go online (Ops radio check).
- [ ] **0815**: First real customer ride accepted.
- [ ] **0830**: Ops eyes the pilot dashboard for the first 30 min.
- [ ] **1000**: Hourly check-in (Ops lead).
- [ ] **1200**: Lunch standup — review first half-day rides.
- [ ] **1700**: Driver shift change check-in.
- [ ] **2030**: Final ride dispatched cleanly.
- [ ] **2300**: Service window closes for the day.
- [ ] **2315**: Daily retro written into the launch report.

## T+1 to T+7 — daily cadence

For each day during the first week:

- [ ] 0700: pre-flight from `daily-monitoring-checklist.md`.
- [ ] Throughout day: incident triage per `support-workflow.md`.
- [ ] 2300: post-mortem entry in `pilot-launch-report.md`.
- [ ] EOD: backup of the rides table (`mysqldump --where='requested_at >= CURDATE()'`)
  shipped to long-term object storage.

## T+14 — pilot retro

- [ ] **Pilot launch report** (`pilot-launch-report.md`) filled in
  with two weeks of numbers.
- [ ] **Driver retention**: % of drivers from week 1 still online in
  week 2.
- [ ] **Customer retention**: % of riders from week 1 with > 1 ride
  in week 2.
- [ ] **Critical incidents**: count + root-cause for each P0.
- [ ] **Cancel rate**: target < 20%. Above 25% → halt pilot, root-cause.
- [ ] **No-drivers rate**: target < 5%. Above 10% → escalate supply.
- [ ] Decision: expand to **Batumi cohort 2** / iterate / abort.

## Sign-off matrix

| Role       | Owner       | Signature | Date |
|------------|-------------|-----------|------|
| Engineering | Eng lead   |           |      |
| Mobile      | Mobile lead |           |      |
| SRE        | SRE lead    |           |      |
| Product    | PM          |           |      |
| Ops        | Ops lead    |           |      |
| Finance    | Fin lead    |           |      |
| Legal      | Legal       |           |      |

Pilot does **not** start until every row is signed.
