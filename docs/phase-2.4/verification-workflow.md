# Driver Verification Workflow

> Phase 2.4 deliverable. End-to-end shape of how a driver moves from
> "pending" to "verified" and onto the platform, plus how the status
> propagates to the customer app (verified badge) and to the dispatch
> service (can-accept-offers gate).

## State machine

```
            ┌─── document submitted ──────┐
            ▼                             │
   pending ─────────────► in_review ──┬───────► verified
            ▲                          │
            │                          ▼
            └──── doc rejected ───── rejected
```

The state lives on `drivers.verification_status` (enum: `pending`,
`in_review`, `verified`, `rejected`). The transition rules:

| From         | To           | Trigger                                                              |
|--------------|--------------|-----------------------------------------------------------------------|
| `pending`    | `in_review`  | First doc upload                                                      |
| `in_review`  | `verified`   | Every required doc approved AND active vehicle verified               |
| `in_review`  | `rejected`   | Any doc rejected                                                      |
| `rejected`   | `in_review`  | Driver re-submits the rejected doc (action `SubmitDriverDocument`)    |
| `verified`   | `in_review`  | Vehicle un-verified OR a previously-approved doc transitions          |

Document state machine (per `driver_documents.status`):

```
   pending ──► approved
       │           │
       ▼           ▼
   rejected ◄── re-review (admin)
```

`SubmitDriverDocument` always deletes prior `pending` / `rejected`
rows for the same `(driver_id, doc_type)` and inserts fresh — keeps
the table from accumulating noisy history.

## Required documents

Pilot pinned at seven types, all matching the existing `doc_type`
enum on `driver_documents`:

| Doc type             | What it is                                                          |
|----------------------|---------------------------------------------------------------------|
| `id_front`           | National ID — front side                                            |
| `id_back`            | National ID — back side                                             |
| `license_front`      | Driving license — front                                             |
| `license_back`       | Driving license — back                                              |
| `insurance`          | Active insurance certificate                                        |
| `vehicle_registration`| Vehicle registration document                                       |
| `selfie_with_id`     | Driver headshot holding their ID (live-capture; selfie match)       |

Background-check is an **ops-procedural** gate documented in
`docs/phase-2.2/driver-onboarding-guide.md` — not stored as a
document for legal reasons. Ops manually flips
`drivers.status = approved` only after the cleared check comes back
from the provider.

## API surfaces

### Driver app

```
POST /api/v1/driver/documents
  multipart/form-data:
    doc_type   : enum (above)
    expires_on : YYYY-MM-DD (optional, when applicable)
    file       : ≤ 8 MB image|pdf|heic|webp

  → 201 { data: { id, doc_type, status, expires_on }, verification: <Badge> }

GET /api/v1/driver/documents
  → 200 { data: [{...}], verification: <Badge> }
```

Both routes require `auth:sanctum + device.bound + not_blocked` and
the `ability:driver` token scope. Rate limit: 10 uploads / min /
driver.

### Customer app (read-only)

The active-ride driver card includes the `verification` badge object
inline (no separate endpoint). For ad-hoc lookup:

```
GET /api/v1/customer/rides/{ulid}
  → 200 { ..., driver: { ..., verification: { verified: bool, badge: ... } } }
```

### Badge shape

Returned by `DriverVerificationPresenter::describe()`:

```json
{
  "verified": true,
  "verified_at": "2026-05-13T08:11:00+04:00",
  "status": "verified",
  "notes": null,
  "missing": [],
  "expiring_soon": [{"doc_type": "insurance", "expires_on": "2026-06-01"}],
  "vehicle_verified": true
}
```

`missing` lists doc types still needed to advance to `verified`.
`expiring_soon` lists already-approved docs whose `expires_on` is
within `config('safety.documents.expiry_warning_days')` (default 30).

## Vehicle verification

Independent from documents. Admin flips after the depot inspection
in `docs/phase-2.2/driver-approval-checklist.md`. Filament action on
the `VehicleResource` calls `VerifyVehicle::verify()` which:

1. Sets `vehicles.verified_at = now()`.
2. Records the reviewing admin + optional notes.
3. Re-evaluates the parent driver's `verification_status` — flips it
   to `verified` if all docs were already approved.

`unverify()` reverses it (vehicle taken off the road) and downgrades
the driver to `in_review` if they were previously verified.

## Verification gate at runtime

`DriverVerificationPresenter::canAcceptOffers($driver)` is the
canonical check before dispatch routes an offer:

- `verification_status === 'verified'`
- `Driver.status === 'approved'`
- `User.status === 'active'` (i.e. not suspended/banned)
- No `severity = block` fraud flag open

Phase 2.5 will move this into `DriverCandidateResolver` to prevent
the dispatcher from offering rides to unverified drivers.

## Admin review (Filament)

`/admin → Drivers → click driver → Documents tab`

For each pending document the reviewer sees:
- Filename, SHA-256, submitted-at, expires-on.
- The file inline (PDF or image).
- "Approve" and "Reject" buttons. Reject requires a reason; the
  reason flows through to the driver app via the badge's `notes`
  field.

Vehicle verification lives under `/admin → Vehicles → click vehicle`
with the same approve/un-verify pattern.

Driver-level overview lives on `/admin → Drivers → click driver` with
the verification badge prominently displayed and a "Verification log"
audit trail (queries `activity_log` for `log_name = 'safety'`).

## Audit trail

Every action lands in `activity_log` under `log_name = 'safety'`:

| Event                    | Subject       | Properties                                          |
|--------------------------|---------------|-----------------------------------------------------|
| `driver.document.approved` | DriverDocument| doc_type, driver_id, notes                          |
| `driver.document.rejected` | DriverDocument| doc_type, driver_id, notes                          |
| `vehicle.verified`         | Vehicle      | vehicle_id, driver_id, notes                        |
| `vehicle.unverified`       | Vehicle      | vehicle_id, driver_id, notes                        |
| `user.suspended`           | User         | reason                                              |
| `user.banned`              | User         | reason                                              |
| `user.reinstated`          | User         | reason                                              |
| `sos.raised`               | SosEvent     | ride_id, location                                   |
| `sos.acknowledged`         | SosEvent     | —                                                   |
| `sos.resolved`             | SosEvent     | resolution                                          |
| `fraud.flag_raised`        | FraudFlag    | kind, severity, subject_user_id                     |
| `fraud.flag_resolved`      | FraudFlag    | notes                                               |
| `complaint.submitted`      | SupportTicket| category, priority, ride_id                         |

Mirrored to `storage/logs/security.log` (daily-rotated, 90-day
retention per `config/logging.php`).

## Permissions (admin side)

| Permission                      | Granted to (default) |
|---------------------------------|----------------------|
| `drivers.documents.review`      | Ops, Engineering     |
| `vehicles.verify`               | Ops                  |
| `users.suspend`                 | Ops lead             |
| `users.ban`                     | Ops lead             |
| `users.reinstate`               | Ops lead             |
| `fraud.flag.resolve`            | Ops, Engineering     |
| `sos.acknowledge`               | Ops, on-call         |
| `sos.resolve`                   | Ops lead             |

Permissions are managed via `spatie/laravel-permission`. See
`docs/architecture/security-and-privacy.md` for the role-to-
permission mapping.
