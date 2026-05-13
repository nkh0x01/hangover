# Admin Safety Tools

> Phase 2.4 deliverable. Operator-facing reference: where to click,
> what each action does, and which downstream events fire. Designed
> to be skimmed during a P0 incident.

## Where to look first

When the safety dashboard pings:

1. **Open SOS** → top-left panel. Click in within 60 s of any new event.
2. **Urgent tickets** → top-right.
3. **Open blocking fraud flags** → bottom-left.
4. **Driver verification queue** → bottom-right.

`/admin → Operations → Safety dashboard`

## Action reference

### Acknowledge SOS event
- Path: `/admin → Support → SOS events → Acknowledge`.
- Required: any on-call ops account.
- What it does: `sos_events.status = 'acknowledged'`, stamps the
  actor + timestamp, writes activity-log entry.
- Next step: call the reporter back within 60 s; do not resolve
  until you've spoken to them.

### Resolve SOS event
- Path: same screen as above.
- Form: select `resolved` (real event handled) OR `false_alarm`.
- Side effect: write to activity-log.

### Approve / Reject driver document
- Path: `/admin → Drivers → click driver → Documents tab`.
- Approve: optional notes.
- Reject: required reason (surfaces to the driver via the
  verification badge `notes` field).
- Side effect: if approval was the last gate, driver flips to
  `verified` and gets a push notification (Phase 2.5).

### Verify vehicle
- Path: `/admin → Drivers → Vehicles → click vehicle → Verify`.
- Optional notes.
- Side effect: re-evaluates the parent driver. Flips to `verified`
  if all required docs are approved.

### Unverify vehicle
- Path: same screen.
- Required: reason (mandatory text).
- Side effect: parent driver bumped to `in_review` (loses
  active-rides eligibility immediately).

### Suspend / ban / reinstate user
- Path: `/admin → Customers → click user` (or via the
  `Fraud flags → suspend user` shortcut).
- Suspend: recoverable. `users.status = 'suspended'`, captures the
  reason. The mobile clients get a 403 with `account.suspended` on
  the next request.
- Ban: terminal. `users.status = 'banned'`. Sanctum tokens revoked
  by the listener (Phase 2.5).
- Reinstate: clears `suspended_at`, `suspension_reason`. The user
  can sign in again.

### Resolve fraud flag
- Path: `/admin → Support → Fraud flags`.
- Required: resolution notes.
- Side effect: sets `resolved_at` + `resolved_by_user_id`. The
  flag stays in the table (we never delete) but stops counting
  toward the open-flag KPI.

### Suspend from a fraud flag (one click)
- Path: `Fraud flags → click row → Suspend user`.
- Combines: open the SuspendUser flow with the flag's reason
  pre-filled.

## Filament permission map

| Permission                       | Granted to              |
|----------------------------------|--------------------------|
| `safety.dashboard.view`          | Ops, Engineering, Product |
| `drivers.documents.review`       | Ops, Engineering          |
| `vehicles.verify`                | Ops                       |
| `users.suspend`                  | Ops lead                  |
| `users.ban`                      | Ops lead                  |
| `users.reinstate`                | Ops lead                  |
| `fraud.flag.resolve`             | Ops, Engineering          |
| `sos.acknowledge`                | Ops, on-call SRE          |
| `sos.resolve`                    | Ops lead                  |

Roles live in `spatie/laravel-permission`. Seed in
`database/seeders/PermissionSeeder.php` (Phase 2.5 finalises the
seed).

## Incident timeline view

Per-ride detail page (Filament):

`/admin → Rides → click ride → Incident timeline`

Rows are chronological mix of:
- Ride status transitions (`ride_status_logs`).
- Support tickets linked to the ride.
- SOS events on the ride.
- Fraud flags raised against customer / driver around the ride.

Each row has a `severity` colour (critical / warning / info) so
the eye lands on the worst event first. Read top to bottom for the
narrative of the ride.

## Cross-references

- Customer FAQ for support tier-1: `docs/phase-2.2/customer-support-faq.md`.
- Tier-1 incident workflow: `docs/phase-2.2/support-workflow.md`.
- Cancellation + refund policy: `docs/phase-2.2/cancellation-refund-rules.md`.
- Ride-safety driver-side: `docs/phase-2.2/ride-safety-checklist.md`.
- Verification details: `docs/phase-2.4/verification-workflow.md`.

## Logs to grep during an incident

```
storage/logs/security.log         # tier-1 audit lane
storage/logs/dispatch.log         # ride lifecycle
storage/logs/realtime.log         # broadcast issues
storage/logs/payment.log          # financial actions

# spatie activity log (db-backed)
SELECT * FROM activity_log
 WHERE log_name = 'safety'
 ORDER BY id DESC LIMIT 50;

SELECT * FROM activity_log
 WHERE subject_id = <user_id> AND log_name IN ('safety', 'money')
 ORDER BY id DESC;
```

## P0 incident playbook (single-page summary)

Detailed version: `docs/phase-2.2/support-workflow.md` § "P0
incident".

1. 0–60 s: acknowledge in admin + PagerDuty.
2. 60 s – 5 min: call both parties; ensure 112 contacted if needed.
3. 5–15 min: SRE pulls GPS trace + ride state log + nearby drivers.
4. 15 min – 4 h: insurance contact informed; driver auto-suspended
   via Filament action.
5. 24 h: initial post-mortem.
6. 5 days: full post-mortem; Legal sign-off.

The Filament admin gives you steps 1, 3 (read), 4 (suspend
action), 6 (incident timeline). Steps 2, 5 are out-of-band.
