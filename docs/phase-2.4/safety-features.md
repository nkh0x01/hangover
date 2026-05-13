# Safety Features Summary

> Phase 2.4 deliverable. Catalogue of every safety-and-trust feature
> the platform ships, who can use it, and how it triggers the
> response chain.

## Customer-facing

### Verified-driver badge
Surfaced on the active-ride driver card. Composed of the driver's
verification status + vehicle-verified flag + a "documents expiring
soon" warning. Drives confidence + reduces "is this actually the
driver?" support tickets.

API: `GET /api/v1/customer/rides/{ulid}` includes
`driver.verification`.

### SOS button
Persistent button on every ride card (and the customer's profile
when not in a ride). Posts to:

```
POST /api/v1/safety/sos
  body: { ride_ulid?, lat?, lng?, body? }
  → 201 { data: { id, status, created_at } }
```

Server-side response:
1. `RaiseSosEvent` action creates a `sos_events` row in `open` state.
2. `MoneyAuditLogger`-equivalent (`activity('safety')`) records a P0
   safety event.
3. `storage/logs/security.log` gets a `critical` entry.
4. Filament safety dashboard shows the event within 15 s.
5. Phase 2.5 will add a queued listener that pages the on-call rota
   via Twilio + Slack.

Threshold: rate-limited to 6 posts / min / user (the mobile clients
debounce + confirm before posting).

### Report / complaint button
Same screens as SOS but for non-emergency issues. Posts to:

```
POST /api/v1/safety/complaints
  body: { category, subject, body, ride_ulid?, attachments? }
  → 201 { data: { id, ulid, priority, status } }
```

`category = safety` short-circuits the priority to `urgent` (the
support team's pager threshold).

### Safety screen (mobile, deferred)
The Flutter "Safety" screen on each ride card surfaces the SOS
button, complaint button, and shortcut to call the driver. UI work
ships in Phase 2.5 alongside the mobile-permissions pre-prompt UX.

## Driver-facing

### Document upload + status
Driver app shows a checklist of required documents, each with
status (pending / approved / rejected / expiring) and any
admin notes on rejection.

```
GET /api/v1/driver/documents   → status + verification badge
POST /api/v1/driver/documents  → upload one file
```

### "Why am I not verified" panel
Computed from the verification badge's `missing` + `expiring_soon`
arrays. Tells the driver exactly which doc to submit and when their
existing docs expire.

### Block-aware errors
If a driver is suspended, every authenticated call returns:

```
403 { "error": { "code": "account.suspended", ... } }
```

via the `not_blocked` middleware. The mobile app catches this and
shows the suspension reason + appeal contact, then logs out.

## Admin / Ops-facing

### Safety dashboard
`/admin → Operations → Safety dashboard` — top-of-funnel.

Widgets:
- Open SOS count.
- Urgent ticket count.
- Open blocking fraud flag count.
- Drivers in review.
- Docs expiring in next 30 days.
- Verified driver count.

Tables: open SOS events, driver verification queue, open fraud
flags, urgent complaints.

Refreshes every 15 s.

### Filament resources
| Resource              | Path                            | Capabilities                          |
|-----------------------|----------------------------------|----------------------------------------|
| Drivers               | `/admin → Drivers → Drivers`     | view, edit, verification filter        |
| Driver documents      | (relation on driver)             | approve / reject (via admin actions)   |
| Vehicles              | `/admin → Drivers → Vehicles`    | verify / unverify                      |
| SOS events            | `/admin → Support → SOS events`  | acknowledge + resolve / false_alarm    |
| Fraud flags           | `/admin → Support → Fraud flags` | resolve + suspend-from-flag            |
| Support tickets       | (existing)                       | reply, change status, change priority  |

### Per-ride incident timeline
`IncidentTimelineService::forRide($ride)` returns a chronological
list mixing:
- Ride status changes (from `ride_status_logs`).
- Support tickets attached to the ride.
- SOS events on the ride.
- Fraud flags raised against the customer or driver around the
  ride window.

Used by the Filament ride-detail page (Phase 2.5 wires the view)
and by the support-team triage script.

### Suspend / ban / reinstate
Single action with three flavours via `SuspendUser`:
- `suspend($user, $actor, $reason)` — recoverable; `users.status = 'suspended'`.
- `ban($user, $actor, $reason)` — terminal; `users.status = 'banned'`.
- `reinstate($user, $actor, $reason)` — clears the suspension.

All three:
1. Update `users` row + `suspended_at`/`suspension_reason`/`suspended_by_user_id`.
2. Activity-log under `log_name = 'safety'`.
3. Security-log entry.
4. (Phase 2.5) Listener `RevokeTokensOnSuspension` purges Sanctum
   tokens; `EndShiftOnSuspension` force-closes any active driver
   shift.

## Automatic detection

### Cancellation storm
`FraudDetector::onRideStatusChange()` raises a `ride_fraud / warn`
flag when a customer has ≥ N cancellations in the last M hours.
Defaults: 5 cancellations in 2 hours, both env-tuneable.

### Implausible speed
`FraudDetector::onDriverHeartbeat()` raises a
`manipulated_location / warn` flag when a driver's implied speed
exceeds the threshold (default 200 km/h). Phase 2.5 wires this from
inside the heartbeat ingestion path.

### Multi-device
`FraudDetector::onDeviceRegistered()` raises a `multi_account / info`
flag when a single account is observed on more than 4 unique
device_uuids in 24 h. `info` severity = surface in dashboard, no
auto-suspend.

### Deferred (stubbed in `FraudDetector`)
- `multi_account` by-phone — needs the merge tool.
- `payment_chargeback` — needs card-gateway integration (Phase 2.4
  payment work).
- `document_forgery` — needs biometric IDV (Phase 3).

## Severity ladder

```
info   →  log it, surface in dashboard, no automatic action
warn   →  surface in dashboard with warning colour, ops decides
block  →  auto-disable in `DriverVerificationPresenter::canAcceptOffers`,
          + Phase 2.5 listener auto-suspends the user
```

## Data retention

| Table                  | Retention                                                       |
|------------------------|------------------------------------------------------------------|
| `driver_documents`     | Keep forever (legal). File contents archived 5 years post-off-board. |
| `vehicles`             | Keep forever                                                     |
| `sos_events`           | Keep forever (legal — could be subpoenaed)                       |
| `fraud_flags`          | Keep forever                                                     |
| `support_tickets`       | Keep 5 years, PII redacted thereafter                            |
| `activity_log` (safety) | Keep 5 years                                                     |
| `users.suspended_at`+   | Keep forever                                                     |

## Open hooks (Phase 2.5)

- Page-on-call listener for SOS events (Twilio + Slack).
- Listener that auto-suspends a user when a `severity = block` flag
  is raised.
- Wire `FraudDetector::onDriverHeartbeat()` into the existing
  `IngestLocationHeartbeat` action.
- Mobile safety-screen UI (Flutter).
- Customer post-trip rating extended with "felt unsafe" tag → opens
  an automatic complaint with severity `urgent`.
