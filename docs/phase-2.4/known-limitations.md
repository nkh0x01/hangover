# Phase 2.4 — Known Limitations

> Honest list of things this phase deliberately did NOT solve, with
> a brief reason + tracking phase for each. Use this when scoping
> Phase 2.5 or answering "why doesn't X work yet" during pilot.

## Mobile

### L1 — No native safety-screen UI yet
The Flutter customer + driver apps don't ship a dedicated safety
screen. The backend SOS + complaint endpoints work; the apps just
don't surface them. Buttons land in Phase 2.5.

**Risk during pilot:** drivers and customers can't easily trigger
SOS without ops walking them through it.

**Mitigation:** ops hotline + safety-dashboard manual flagging.

### L2 — No native "verified" badge UI yet
The badge object is in the API response. Customer app surfaces it
in Phase 2.5 with the proper icon + tap-for-detail interaction.

### L3 — No push notification on document approval / rejection
The driver finds out by refreshing their documents list. FCM-based
notification ships in Phase 2.5 as `driver.document.approved` /
`.rejected` push kinds (mobile-side enums are already declared in
`mobile/packages/core/lib/src/push/push_service.dart`).

## Backend integrations

### L4 — Heartbeat-based speed detection not wired
`FraudDetector::onDriverHeartbeat()` exists but isn't called from
`IngestLocationHeartbeat`. The pump computes implied speed but doesn't
forward it. Phase 2.5 wires this.

### L5 — Multi-account-by-phone detection deferred
`FraudDetector` doesn't dedupe by phone or by IMEI. The data exists
on `user_devices.device_uuid` + `users.phone_e164` but the rule
needs a merge tool first. Phase 3.

### L6 — Payment-chargeback flag not wired
Card-payment gateways are stubbed (Phase 2.3). When BOG/TBC/Stripe
go live, the webhook handlers will raise `payment_chargeback`
flags. Phase 2.4.5 / 2.5.

### L7 — Document forgery / face match not wired
`selfie_with_id` is uploaded but not biometrically compared against
the `id_front`. Manual reviewer-eye for pilot. Phase 3 ships an
IDV provider integration.

### L8 — Background check is a manual procedural step
Background checks happen out-of-band via SAFE.ge (or local
equivalent). The result is recorded by ops flipping
`drivers.status = approved`. There's no `background_check`
document type — the artefact is filed in physical onboarding folders
(see `docs/phase-2.2/driver-onboarding-guide.md`).

## Operational

### L9 — No automatic page-on-SOS yet
SOS events land in the dashboard + security log within 15 s, but
there's no PagerDuty / Twilio / Slack listener wired. Ops must
have the dashboard open during pilot hours (07:00–23:00). Phase 2.5
ships `App\Modules\Support\Listeners\NotifyOpsOfSos`.

### L10 — No auto-suspend on blocking fraud flag
A `severity = block` flag is created, but the user isn't
automatically suspended — ops must click the "Suspend from flag"
action manually. The listener (`AutoSuspendOnBlockingFlag`) is the
last piece of Phase 2.5.

### L11 — No automatic token revocation on suspension
When `SuspendUser::suspend()` runs, the user's Sanctum tokens stay
valid. The `not_blocked` middleware rejects every request with 403
so they can't actually do anything, but the token isn't revoked.
The listener `RevokeTokensOnSuspension` is queued onto the default
lane in Phase 2.5.

### L12 — No end-shift-on-suspension hook
If a driver is suspended while online, their `Driver.online` row
stays `true`. The dispatcher's eligibility check
(`canAcceptOffers`) refuses to route them new offers, but the
"Online" badge on the live-rides map is stale. Phase 2.5 listener
flips them offline.

### L13 — Vehicle inspection is not photo-verified by software
We store photos uploaded by the driver, but the actual depot
inspection is a human-eye check (see
`docs/phase-2.2/driver-approval-checklist.md`). No CV step in this
phase.

## Privacy + compliance

### L14 — No GDPR data-deletion endpoint
Suspended/banned users can request deletion; today that's a manual
SQL operation by Engineering. Phase 3 ships a customer-facing
"delete my account" flow.

### L15 — Activity log retention is not enforced
We say "5 years" for the safety log but there's no daily prune
job yet. Add to the Phase 2.5 scheduler.

### L16 — Document storage encryption-at-rest is filesystem-level
Documents land on the `drivers` disk (S3 in prod). S3 server-side
encryption is on by default but we don't add an application-layer
envelope around them. Phase 3 adds field-level encryption for the
sensitive subset (ID + license).

## Testing gaps

### L17 — No HTTP-level test for SOS / complaint routes
We have unit + feature tests for the actions. The actual HTTP
boundary (auth → middleware → controller) is covered indirectly via
the action tests. End-to-end HTTP test in Phase 2.5 once the
multipart-upload test helper is in place.

### L18 — No test for the safety dashboard rendering
Filament pages need a browser-driven test (Pest + Dusk). Skipped
during pilot — visual smoke during testing-checklist.md replaces
it. Phase 3 adds Dusk runs to CI.

### L19 — No load test on safety endpoints
SOS + complaints are low-volume but during an incident we might
get spikes. Pilot rate-limits (6/min for SOS, 30/min for
complaints) should be sufficient. Phase 3 runs k6 against them.

## What this phase does NOT add

Per the user's "do not add package delivery yet" + "passenger
safety only" constraint:

- No package / parcel delivery features.
- No food delivery features.
- No two-passenger or family-account flows.
- No corporate-account safety controls.
- No women-only-rider or women-only-driver matching (deferred to
  product discussion).
- No real-time route monitoring with deviation alerts.
- No mandatory mid-ride check-ins.
- No "share my trip" deep-link out to family.

Each of these has a strong product case but lands in Phase 3+.
