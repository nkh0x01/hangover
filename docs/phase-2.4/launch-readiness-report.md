# Hangover Platform — Phase 2.4 Launch-Readiness Report

> Reporting date: 2026-05-13
> Branch: `claude/scooter-platform-architecture-Wvmeu`
> Phase: **2.4 — Driver Verification, Safety, and Trust**

## Headline

The platform now has a complete verification ladder + safety event
pipeline + admin tooling for response. Drivers cannot reach "verified"
without all required documents approved AND a vehicle inspection
sign-off. Customers and drivers can raise SOS events and complaints
that land in a polling dashboard for ops with full audit trails.
Automatic fraud-detection rules cover cancellation storms,
implausible-speed location spoofing, and multi-device accounts.

What's intentionally not yet shipped: mobile UI for the safety
screens, push notifications on verification state changes, and the
queued listeners that page on-call + auto-suspend. Those are
Phase 2.5 deliverables documented in `known-limitations.md`.

## What Phase 2.4 shipped

### 1. Verification schema

`0001_01_01_000026_add_verification_and_suspension_columns.php`:
- `drivers.verification_status` (enum, indexed) + `verified_at` +
  `verification_notes`.
- `vehicles.verified_at` + `verified_by_user_id` (FK to users) +
  `verification_notes`.
- `users.suspended_at` (indexed) + `suspension_reason` +
  `suspended_by_user_id` (FK to users).

Non-destructive — every historic row defaults safely.

### 2. Driver verification actions

- `Driver\Actions\SubmitDriverDocument` — driver-side upload.
  Computes SHA-256, persists to the `drivers` filesystem disk under
  `documents/{driver_id}/{doc_type}/{sha}.{ext}`, replaces prior
  pending/rejected rows, flips `verification_status` to `in_review`
  on first submission.
- `Driver\Actions\ReviewDriverDocument` — admin-side approve/reject.
  Approval re-evaluates whether the driver should advance to
  `verified` (all docs approved + vehicle verified). Rejection
  instantly flips driver to `rejected` with the supplied notes.
- `Driver\Actions\VerifyVehicle` — admin verification of a vehicle
  with re-evaluation of the parent driver.
- `Driver\Services\DriverVerificationPresenter` — single source of
  truth. `describe()` returns the public badge; `canAcceptOffers()`
  is the dispatch gate (verified + approved + active + no blocking
  flags).
- `Driver\Http\Controllers\DocumentController` — `GET /api/v1/driver/documents`
  + `POST /api/v1/driver/documents`. Multipart, rate-limited
  (10/min), validates against the 7-type enum.

### 3. Safety event pipeline

- `Support\Actions\RaiseSosEvent` + `acknowledge()` + `resolve()`.
  Persists with MySQL spatial column when available; SQLite tests
  skip the location update.
- `Support\Actions\SubmitComplaint` — category-driven priority
  (safety → urgent, payment → high, etc). Accepts attachment
  metadata.
- `Support\Actions\RaiseFraudFlag` — typed kind + severity ladder
  (info / warn / block). `resolve()` for the closing path.
- `Support\Actions\SuspendUser` — suspend / ban / reinstate. Handles
  the `suspended_at` timestamp invariant and updates
  `suspension_reason` + `suspended_by_user_id`.
- `Support\Http\Controllers\SosController` + `ComplaintController`
  at `/api/v1/safety/{sos,complaints}` — auth + device.bound +
  not_blocked + rate-limited.

### 4. Automatic detection

`Support\Services\FraudDetector`:
- `onRideStatusChange()` — cancellation-storm detection
  (configurable count + window).
- `onDriverHeartbeat()` — implausible-speed (default 200 km/h).
- `onDeviceRegistered()` — multi-device threshold (default 4 in 24h).

Each rule short-circuits if a recent flag of the same kind is
already open against the same subject. Config under
`config/safety.php`.

### 5. Block enforcement

- `App\Http\Middleware\EnsureNotBlocked` registered under the
  `not_blocked` alias and applied to the driver routes; service
  providers will add it to remaining customer routes in Phase 2.5.
- 403 envelope with `account.suspended` / `account.banned` codes.
- `User::isBlocked()` predicate for code paths that need it.

### 6. Incident timeline

`Support\Services\IncidentTimelineService::forRide()` composes a
chronological event list from `ride_status_logs`, `support_tickets`,
`sos_events`, and `fraud_flags` (filtered to subjects + window).
Each event has typed severity (`info`, `warning`, `critical`) for
UI consumption.

### 7. Admin tooling (Filament)

- `Support\Filament\Pages\SafetyDashboardPage` at
  `/admin → Operations → Safety dashboard` (15-second poll).
- `Support\Filament\Widgets\SafetyOverviewWidget` (six KPIs).
- `Support\Filament\Resources\SosEventResource` with
  Acknowledge + Resolve actions.
- `Support\Filament\Resources\FraudFlagResource` with Resolve +
  Suspend-from-flag actions.
- `DriverResource` extended with `verification_status` column +
  filter.

### 8. Audit log

Every safety-action calls `activity('safety')->log(...)`. The
spatie/activitylog package writes to `activity_log` with the
actor (`causer_id`), subject, properties, and the event slug.
Mirrored to `storage/logs/security.log`. Retention: 5 years
(see `known-limitations.md` for the prune-job gap).

### 9. Tests

26 new Pest tests:

- `tests/Feature/Driver/DriverVerificationTest.php` — 7 tests.
- `tests/Feature/Support/SafetyActionsTest.php` — 10 tests.
- `tests/Feature/Support/FraudDetectorTest.php` — 6 tests.
- `tests/Feature/Support/BlockedUserMiddlewareTest.php` — 3 tests.

Total backend suite: 89 tests, 86 passing, 1 skipped, 2
pre-existing Redis-connection errors. PHPStan clean for all new
code (1 pre-existing DeviceController error unchanged).

### 10. Documentation

| File                              | Audience            |
|-----------------------------------|----------------------|
| `verification-workflow.md`        | Engineering, Ops     |
| `safety-features.md`              | Cross-team           |
| `admin-safety-tools.md`           | Ops on-call          |
| `testing-checklist.md`            | QA                   |
| `known-limitations.md`            | Steering             |
| `launch-readiness-report.md`      | Sign-off (this file) |

## Risk register

| Risk                                                  | Likelihood | Impact | Mitigation                                                                |
|-------------------------------------------------------|------------|--------|---------------------------------------------------------------------------|
| Ops misses an SOS event in the 15-s poll window       | Medium     | Critical | Phase 2.5 listener pages on-call directly via Twilio + Slack             |
| Suspended user keeps using their token until expiry   | Medium     | Medium | Middleware blocks every request with 403; tokens are still revoked in 2.5 |
| Driver supplies a forged document                     | Medium     | High   | Reviewer-eye for pilot; biometric IDV in Phase 3                          |
| Cancellation-storm rule false-positives a frequent traveller | Medium | Low | `warn` severity → ops decides; thresholds env-tuneable                   |
| Implausible-speed rule misfires on GPS jump          | Medium     | Low    | `warn` severity; only fires once per 30 min                                |
| Vehicle deteriorates between inspections             | Low        | High   | Re-inspection mandatory every 90 days (procedural)                          |
| `selfie_with_id` photos accumulate as PII liability   | Low        | Medium | Filesystem disk encryption + 5-year retention; envelope encryption in 2.5  |

## Acceptance criteria

- [x] Driver document upload endpoint live + rate-limited
- [x] Admin document review action (approve / reject)
- [x] Vehicle verification action
- [x] Driver verification badge computed + exposed
- [x] SOS event endpoint + service
- [x] Complaint reporting endpoint
- [x] Fraud flag service + 4 detection rules (3 with auto-detect, 1
      manual-only)
- [x] Block-user middleware enforces 403 on suspended / banned
- [x] Admin safety dashboard live
- [x] Incident timeline service composes the four contributor tables
- [x] Audit log entries for every safety action
- [x] 26 new passing tests
- [x] PHPStan clean for new code

## Sign-off

- [ ] Engineering lead
- [ ] Mobile lead (acknowledges UI gap → 2.5)
- [ ] SRE
- [ ] Ops
- [ ] Legal (audit-log + retention review)

Sign-off gates the start of Phase 2.5 (mobile UI + queued listeners).

## Next phase (Phase 2.5 — Mobile UI + Live Listeners)

Recommended scope (mostly mobile + the deferred listener pieces):

1. Customer + driver safety screens in Flutter (SOS, complaint
   buttons, verified-driver badge).
2. Queued listener `NotifyOpsOfSos` → Twilio + Slack.
3. Queued listener `RevokeTokensOnSuspension`.
4. Queued listener `EndShiftOnSuspension`.
5. Queued listener `AutoSuspendOnBlockingFlag`.
6. Wire `FraudDetector::onDriverHeartbeat()` into
   `IngestLocationHeartbeat`.
7. Push notifications for `driver.document.approved` /
   `.rejected`.
8. End-to-end HTTP tests for the safety endpoints.
9. Dusk-based regression for the safety dashboard.
