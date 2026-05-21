# Driver Onboarding Guide — Pilot Operations

> Phase 2.2 deliverable. Step-by-step recipe for taking a candidate
> driver from "interested" to "Online, earning money" during the
> controlled pilot. Owned by the Ops team. Lives in `docs/phase-2.2/`
> so it stays version-controlled alongside the platform changes.

Audience: Ops staff onboarding drivers in person. Not the driver
themselves — the driver gets the shorter `driver-training-guide.md`.

Time budget: **~90 minutes per driver** (allocated as 20 doc capture
+ 40 admin entry & training + 30 first ride shadow).

## 1. Pre-screen call (10 min)

Before booking the in-person session, run a short phone call:

- [ ] Confirms they have a smartphone running Android 6+ (API 23) or
  iOS 13+.
- [ ] Confirms their motorcycle / scooter is registered to them or to
  a family member at the same address.
- [ ] Confirms they are 21+ with at least 2 years of driving experience.
- [ ] Confirms they have valid insurance in their name (or named on
  the vehicle owner's policy).
- [ ] No DUI / no violent offence in the last 5 years (asked explicitly).
- [ ] Available for at least 20 h/week during the pilot.
- [ ] Speaks at minimum Georgian or English.

If all yes → schedule the in-person session.

## 2. Document collection (20 min)

The driver brings **originals and clear photos** of:

| Document                          | Required | Notes                                              |
|-----------------------------------|----------|----------------------------------------------------|
| Government-issued ID              | Yes      | Front + back. Photo legible.                       |
| Driver license                    | Yes      | Class B + motorbike (A/A1) — check expiry > 6 mo  |
| Vehicle registration              | Yes      | Match name on ID or family certificate            |
| Vehicle technical inspection      | Yes      | Valid > 60 days                                    |
| Insurance certificate             | Yes      | OSAGO + (recommended) third-party                  |
| Vehicle photos                    | Yes      | Front, back, both sides, dashboard, plate close-up |
| Driver headshot                   | Yes      | Clear face, no sunglasses, plain background        |
| Tax / sole-trader registration    | Optional | Required for VAT-eligible earnings > 30k GEL/yr   |
| Bank account / Wallet number      | Yes      | For weekly payouts                                 |
| Background check consent          | Yes      | Sign the consent form attached to this guide      |

Ops captures every document as a high-res JPEG → uploads to the
driver record via `admin → Drivers → Documents`.

Reject and re-schedule if:
- Any document is expired.
- Vehicle photos show damage that would prevent safe passenger transport.
- License class is wrong for the vehicle.

## 3. Background check (out-of-band, 2-5 days)

Submit ID + consent form to the cleared local background-check
provider (currently SAFE.ge or equivalent). Track via the
`driver_documents` table — set `background_check_status` = `pending`
on submission, `cleared` on receipt.

The driver cannot go online until `background_check_status = cleared`.

## 4. Vehicle inspection (20 min)

In-person at the depot:

- [ ] Brakes engage smoothly; no grinding.
- [ ] Both indicators + brake light + headlight functional.
- [ ] Mirrors present + intact.
- [ ] Tires: at least 2 mm tread, no visible cracks.
- [ ] No visible structural damage.
- [ ] Helmet provided (driver + passenger). DOT or ECE certified.
- [ ] Reflective vest for the driver.
- [ ] (Recommended) phone holder mounted within thumb reach.

If anything fails → driver gets a 7-day window to fix and re-inspect.

## 5. Admin entry (15 min)

Use `admin → Drivers → New driver`:

1. **User**: Create a new `User` row with the driver's phone number.
   `type = driver`.
2. **Driver**: Link to user. Set:
   - `city_id` to Tbilisi (1) or Batumi (2).
   - `status = pending_approval` (until trainer signs off in step 7).
   - `commission_pct = 15` (pilot default).
3. **Vehicle**: Create a `Vehicle` row linked to the driver.
   - Plate, make, model, year, color.
   - Photos URL.
   - `is_active = false` (we activate at the end of training).
4. **Documents**: Attach the photos uploaded in step 2 with the
   correct `type` (`id`, `license`, `registration`, `insurance`,
   `tech_inspection`, `headshot`).
5. **Bank account**: Capture in the `payouts` tab.

## 6. App installation (10 min)

- [ ] Install **Hangover Driver** on the driver's phone:
  - Android: hand over the QR code that opens the Play Store internal-
    test link, OR sideload `driver-prod.apk` from a thumbstick.
  - iOS: send the TestFlight invite to the email on file.
- [ ] Driver opens the app, signs in with their phone, OTP `000000`
  is **not** valid in prod — the real SMS arrives.
- [ ] Driver grants location (we ask *While using the app* first).
- [ ] Driver grants notification permission.
- [ ] Driver completes the in-app profile (photo, vehicle confirm).

## 7. Training ride (30 min)

Use `driver-training-guide.md` as the script. The driver:

- [ ] Watches a 5-min explainer of the lifecycle (offer → accept →
  arriving → arrived → start → complete).
- [ ] Watches the trainer's phone receive a test offer, accept it,
  navigate to a pickup point 800 m away, fake-pickup an Ops rider,
  drive a 2 km route, complete with cash.
- [ ] Repeats the same flow on their own device with an Ops rider.
- [ ] **Tagged `is_test_ride = true`** (the rider's phone must be on
  the `PILOT_TEST_PHONES` list — verify before pressing Confirm).

Sign-off criteria for the trainer:
- Accepted the offer in < 5 s.
- Followed the navigation to within 50 m of the pin.
- Tapped each state transition in the correct order.
- Completed the cash settlement screen without confusion.
- Asked at least one informed question.

If any of those fails → run the test ride again. After two failures,
escalate to the Ops lead.

## 8. Activation (5 min)

In `admin → Drivers → {driver}`:

- [ ] Set `Driver.status = active`.
- [ ] Set `Vehicle.is_active = true`.
- [ ] Set `current_vehicle_id` on the driver row.
- [ ] Add the driver's phone to **PILOT_TEST_PHONES** if they double
  as a customer tester. Otherwise leave it off the list — their
  rides are real.
- [ ] Hand over the printed "Day-1 quick reference" card (laminated).

The driver can now toggle **Online** in the app and start receiving
real offers.

## 9. Day-1 shadow (during their first 4 hours online)

Ops keeps an eye on the pilot dashboard. For each driver's first 3
real rides:

- [ ] Watch the ride in the live monitor.
- [ ] Confirm acceptance latency < 10 s.
- [ ] Confirm arrival at pickup within the ETA + 5 min.
- [ ] Confirm completion within the estimated duration + 5 min.
- [ ] Confirm cash settlement matches the quoted fare (driver can
  collect tip on top — that's not platform-tracked).

If anything looks off → call the driver immediately.

## 10. Day-7 check-in (15 min, phone call)

After a week online:

- [ ] How many hours did they actually work?
- [ ] Any rides where the app misbehaved? (Capture as Sentry events.)
- [ ] Any rider complaints they want to flag back to us?
- [ ] Any feature requests?

Capture the answers in `admin → Drivers → {driver} → Notes`.

## Reject / off-board

A driver moves to `status = suspended` and is taken off the platform if:

- Background check fails or comes back with concerning info.
- Two confirmed safety incidents.
- More than 20% no-show rate over a rolling 50 rides.
- Repeated app misuse (offline-then-online thrashing, fake completions).
- Voluntary withdrawal.

The off-board procedure is documented in `support-workflow.md` §6.
