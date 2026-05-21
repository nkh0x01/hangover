# Phase 2.4 Testing Checklist

> Manual QA scenarios for the verification + safety layer. Run
> through these against the staging environment before each
> production deploy that touches the safety code path.
>
> Automated coverage: 26 Pest tests under `tests/Feature/Driver/`
> and `tests/Feature/Support/`. The scenarios below verify the
> integration of those units against a real database + storage.

Pre-flight: backend on staging, `MAIL_MAILER=array`,
`PUSH_DRIVER=null`, fresh sqlite ok; live MySQL gives you the
spatial SOS-location column for completeness.

## A. Driver document upload

### A1 — Happy path approval
1. Onboard a fresh driver (admin → Drivers → New) → status
   `pending`, `verification_status = pending`.
2. Sign in as the driver in the mobile flow.
3. Upload each of: `id_front`, `id_back`, `license_front`,
   `license_back`, `insurance`, `vehicle_registration`,
   `selfie_with_id`.
4. Verify the badge after each upload: `missing` shrinks, status
   stays `in_review`.
5. Switch to admin. Approve each document in turn. `verification_status`
   stays `in_review` until vehicle is verified.
6. Verify the vehicle. Driver flips to `verified`, `verified_at`
   set.
7. Customer-side: book a ride with this driver. Driver card shows
   the verification badge.

### A2 — Reject + re-submit
1. Upload a license.
2. Admin → reject with reason "blurry".
3. Driver flips to `rejected`, badge shows the notes.
4. Driver app shows the reason on the document checklist.
5. Driver re-uploads. Old row is deleted; new row is `pending`.
   Driver flips back to `in_review`.
6. Admin approves. Re-run A1 step 6.

### A3 — Expiring document
1. Approve all docs but set `expires_on` on `insurance` to 10 days
   from now.
2. Verify the vehicle so the driver hits `verified`.
3. The badge should include `expiring_soon: [{insurance, <date>}]`.
4. The driver app should show a banner "Renew your insurance by …".

### A4 — Vehicle un-verified mid-flight
1. Driver currently `verified`.
2. Admin → Vehicles → un-verify (reason: "missed inspection").
3. Driver flips to `in_review` immediately. Customer cannot be
   matched with this driver on next ride request (eligibility
   check ships in 2.5 — for now confirm via SQL that
   `verification_status = 'in_review'`).

## B. SOS

### B1 — Customer SOS during ride
1. Active ride in progress.
2. From the customer app, tap the safety screen → Send SOS.
3. POST `/api/v1/safety/sos` with `ride_ulid`, `lat`, `lng`, `body`.
4. Response: `201 { data: { id, status: 'open', ... } }`.
5. Admin → Safety dashboard: event appears within 15 s.
6. `storage/logs/security.log` has a `sos.raised` `CRITICAL` line.

### B2 — Acknowledge → resolve flow
1. With an open SOS, admin clicks Acknowledge. Status → `acknowledged`.
2. Click Resolve, pick `resolved`. Status → `resolved`.
3. Run again, this time pick `false_alarm`. Status →
   `false_alarm`.

### B3 — Rate-limit
1. Spam POST `/api/v1/safety/sos` 7 times in 10 s from one device.
2. The 7th call returns 429.

## C. Complaint reporting

### C1 — Safety complaint pre-priority
1. Customer POST `/api/v1/safety/complaints` with
   `category: safety`.
2. Response: `priority: 'urgent'`.
3. Admin → Safety dashboard → urgent tickets table shows the row.

### C2 — Other categories
1. Submit a `payment` complaint → `priority: 'high'`.
2. Submit a `app_bug` complaint → `priority: 'normal'`.
3. Submit an unknown-category complaint → 422 with validation
   error.

### C3 — Attachments
1. Submit a complaint with up to 5 attachment metadata blobs.
2. Verify the message row has the attachments JSON column populated.

## D. Fraud flags

### D1 — Cancellation storm
1. As one customer, request + cancel 5 rides in 30 minutes (use the
   admin "force cancel" action to skip the matching timer).
2. After the 5th cancel, hit `/admin → Support → Fraud flags`.
3. A new flag should appear: kind `ride_fraud`, severity `warn`,
   evidence includes the count + threshold.

### D2 — Implausible speed
1. Currently scripted only: trigger
   `FraudDetector::onDriverHeartbeat($user, 250.0)` from tinker.
2. Verify a `manipulated_location / warn` flag is created.
3. Phase 2.5 wires this into the live heartbeat path.

### D3 — Resolve a flag
1. Open the safety dashboard.
2. Click Resolve on any flag. Form requires notes.
3. Submit. The flag's `resolved_at` is set; safety-dashboard count
   drops.

### D4 — Suspend from a flag
1. With an open flag, click "Suspend user".
2. Form requires reason (defaults to the flag's evidence).
3. After submit: user's `status = 'suspended'`, `suspended_at` set.

## E. Block enforcement

### E1 — Suspended user
1. Suspend a customer via admin.
2. From their app, make any authenticated request.
3. Server returns `403 { error: { code: 'account.suspended' } }`.

### E2 — Reinstate
1. Reinstate the same user.
2. Their `suspended_at` / `suspension_reason` are NULL.
3. Their requests now succeed.

### E3 — Banned vs suspended
1. Ban a user.
2. `users.status = 'banned'`, `User::isBlocked() = true`.
3. Reinstate works the same way as suspend.

## F. Incident timeline

### F1 — Per-ride timeline
1. Force a ride to go through: requested → cancelled.
2. Submit a `driver_behaviour` complaint linked to the ride.
3. Raise a fraud flag against the driver around the time.
4. `IncidentTimelineService::forRide($ride)` returns all three
   events in chronological order with `severity` levels.

## G. Audit log

### G1 — Activity log entries
After running any action (suspend, document approve, SOS resolve):

```sql
SELECT log_name, description, properties, causer_id, created_at
  FROM activity_log
 WHERE log_name = 'safety'
 ORDER BY id DESC LIMIT 20;
```

Each action above should produce exactly one row in the table with
the actor (causer_id), the subject, and a meaningful `properties`
JSON.

## H. Verification badge in API

### H1 — Customer fetches driver card
1. Active ride.
2. GET `/api/v1/customer/rides/{ulid}` (current shape — Phase 2.5
   may add a dedicated badge endpoint).
3. Response includes a `driver.verification` object matching the
   schema in `verification-workflow.md`.

### H2 — Badge during in-review
1. Driver with 5/7 docs approved, vehicle unverified.
2. Badge: `verified: false`, `missing: [...remaining types...]`,
   `vehicle_verified: false`.

### H3 — Badge while suspended
1. Verified driver gets suspended.
2. Badge still says `verified: true` (verification is independent
   of suspension), BUT `DriverVerificationPresenter::canAcceptOffers()`
   returns `false` so dispatch refuses to match.

## I. Regression smoke

After all the above:
- [ ] Run `./vendor/bin/pest`. Same baseline (1 skipped, 2 Redis-
      infra errors, everything else green).
- [ ] Run `./vendor/bin/phpstan`. Only the pre-existing
      DeviceController warning is allowed.
- [ ] Run a full happy-path ride end-to-end (no safety actions) —
      driver gets paid, customer gets the receipt, no Sentry events.

If any of those red, halt the deploy and investigate.
