# Hangover Mobile — Real-Device QA Scenarios

> Phase 2.1 deliverable. Every scenario QA must walk through on **real
> phones** (not just emulators) before staging cuts over to public beta.
> Designed to be run by one tester with two devices, or two testers
> coordinating over chat.

Reference build:
- Customer APK: `build/customer-staging.apk`
- Driver APK:   `build/driver-staging.apk`
- Backend env:  `staging` (API: `https://staging.api.hangover.app`)
- WS broker:    `wss://staging.realtime.hangover.app/app/<key>`

## Test devices

Aim to cover three categories per platform. The first two are
**must-pass**; the third is *nice-to-pass* before launch:

| Tier  | Android sample            | iOS sample          | Why                                     |
|-------|---------------------------|---------------------|------------------------------------------|
| New   | Pixel 8 / Galaxy S24      | iPhone 15 (iOS 18)  | shakedown on flagships                   |
| Old   | Pixel 4a / Galaxy A14     | iPhone SE 2 (iOS 16)| min-OS regression                        |
| Edge  | Xiaomi Redmi / Oppo A18   | iPhone XS (iOS 15.x)| OEM background-killer + small storage    |

Pre-install one customer + one driver APK. Either two devices
(preferred) or one device with both apps installed at the same time
(use the `.dev` flavor for one and `.staging` for the other so the
package names don't collide).

## Setup

Before every QA run:

1. Force-stop both apps, clear data.
2. Pair both devices with the staging backend:
   - Customer phone number: `+995 599 000 001`
   - Driver phone number:   `+995 599 000 002`
   - OTPs are always `000000` on the `staging` flavor.
3. Reset the driver's online/offline state via Filament admin:
   `https://staging.api.hangover.app/admin → Drivers → set status =
   offline, current_vehicle_id = <vehicle>`.
4. On the driver device, sign in and toggle online.

Each scenario records a `PASS`/`FAIL` plus a short note. When a
scenario fails, file an entry in `launch-blockers.md`.

---

## A. Onboarding & authentication

### A1 — Customer login happy path
1. Open customer app.
2. Splash → phone screen with the country picker pre-selected to GE.
3. Tap **Continue**.
4. Enter `+995 599 000 001` → **Send OTP**.
5. OTP screen: enter `000000` → **Verify**.
6. Lands on the customer home map.

**Expect:** Each transition takes < 600ms. Brand-mark animation runs.
Locale-correct copy. No flash-of-error toast.

### A2 — Customer wrong OTP / retry
1. From the OTP screen, enter `111111` three times.
2. First two: see "Incorrect code" status pill, OTP cells reset.
3. Third attempt: "Too many attempts. Try again in 30 s."
4. Tap **Resend** after the cooldown completes.

**Expect:** Resend button is disabled during cooldown with a countdown.
Backend returns `429` on the third attempt — the UI does NOT crash.

### A3 — Driver login + missing vehicle
1. Sign in as a driver whose `current_vehicle_id` is `NULL`.
2. Expect the home screen's online toggle to be disabled with a
   contextual message: "Add a vehicle to go online."

### A4 — Sign out wipes local state
1. After login, navigate to profile, tap **Sign out**.
2. Force-stop and relaunch.
3. The app should land on the phone-entry screen, not the home map.

---

## B. Permissions

### B1 — Location permission denied (customer)
1. Fresh install, sign in.
2. Customer home page: when the OS prompt appears, tap **Don't allow**.
3. Map should still render (default city center) with a banner
   "Location access is off. Tap to enable."
4. Tapping the banner opens the system Settings deep link.

### B2 — Location upgrade flow (driver)
1. Fresh install, sign in as driver.
2. Toggle online — OS prompts for *While using the app*.
3. Grant.
4. After 30 s of in-trip GPS, the OS escalates to *Always allow*
   (Android 11+, iOS) — accept.
5. Verify the in-trip ride continues to receive position updates
   while the device is locked.

### B3 — Notification permission (Android 13+)
1. Fresh install on Pixel 8 / Android 14.
2. On first foreground frame, OS prompts for POST_NOTIFICATIONS.
3. Deny. Confirm no crash; FCM token registration is still attempted
   (foreground delivery works without notification permission).
4. Settle into the home page, then re-enable notifications via
   Settings → Apps → Hangover → Notifications.
5. Confirm a push test message (Filament admin → "send test push to
   user") arrives.

---

## C. End-to-end ride flow

### C1 — Happy path
1. Customer:
   - From home, tap the "Where to?" search.
   - Pick a destination ≤ 3 km away.
   - Confirm pickup pin (or move it).
   - Tap **Confirm fare** on the fare-estimate sheet.
2. Driver:
   - Within 5–10 s, the incoming-offer modal pops with the fare
     summary + countdown timer.
   - Tap **Accept** before the timer expires.
3. Customer:
   - Screen transitions to "Driver assigned" with driver name,
     vehicle, plate, and ETA pill.
4. Driver:
   - Tap **Arriving** → status pill on customer updates to "Driver
     arriving".
   - Tap **Arrived** at the pickup → customer sees "Driver arrived".
5. Driver:
   - Tap **Start trip**.
6. Customer:
   - Trip card flips to "On trip" with the destination + ETA.
7. Driver:
   - At destination, tap **Complete trip**.
   - Cash sheet appears with the final fare.
8. Customer:
   - Lands on the rating screen + share-rating prompt.

**Expect:** Each state transition propagates within 2 s on a fast
network. Counterparty UI never shows stale state for more than 5 s.

### C2 — No drivers nearby
1. Disable every staging driver (admin: set status = offline).
2. Customer requests a ride.
3. After 60 s the customer sees "No drivers available — try again"
   with a primary CTA to retry.

### C3 — Driver declines the offer
1. With C1 setup, driver swipes/decline on the incoming-offer modal.
2. Within 5 s the customer should see the offer routed to a second
   driver (use a second driver device for this scenario).
3. If only one driver exists, customer sees "Still searching…" until
   the 60-s timeout.

### C4 — Customer cancels before pickup
1. C1 up to "Driver assigned".
2. Customer taps **Cancel ride**.
3. Confirm cancellation modal → confirm.
4. Customer returns to home with a snackbar "Ride cancelled".
5. Driver sees "Customer cancelled" toast + returns to online state.

### C5 — Driver cancels (in extremis)
1. C1 up to "Driver arriving".
2. Driver taps the menu → **Cancel ride** → confirm.
3. Customer sees "Driver cancelled" + offer routed to next driver.

### C6 — Cancellation policy (cancel after start)
1. C1 up to "On trip".
2. Customer taps cancel.
3. The app should NOT permit cancellation in the on-trip state
   (button is disabled / hidden).

---

## D. GPS + map quality

### D1 — GPS accuracy
1. Walk 200 m while the customer app shows the live map.
2. The pickup pin should follow within ~5 m of the actual position.
3. On the driver app during an active ride, the customer should see
   the driver pin updating every 3–5 s with no obvious jumps > 30 m.

### D2 — Background GPS (driver)
1. Driver C1 up to "On trip".
2. Lock the screen and put the phone face-down for 60 s.
3. Wake, unlock — the ride should still be active, with smooth
   trajectory on the customer side. No "Driver disconnected" banner.

### D3 — Foreground service notification (Android)
1. While driver is in-trip on Android, pull down the notification
   shade.
2. Expect a sticky foreground-service notification:
   "Hangover Driver — sharing live location during ride".
3. The notification should be undismissible until the trip ends.

### D4 — Indoor / weak GPS
1. Start a ride inside a building.
2. The pickup pin should resort to last-known location with a
   "GPS signal weak" hint after 10 s.

---

## E. Push notifications

### E1 — Ride offer push (driver)
1. Driver app backgrounded + screen locked.
2. Customer requests a ride.
3. Phone wakes with a heads-up notification, vibrates + plays the
   offer sound.
4. Tap the notification → app foregrounds directly into the offer
   modal.

### E2 — Ride status pushes (customer)
1. Customer requests a ride, backgrounds the app.
2. As the driver transitions through accept → arriving → arrived,
   the customer phone shows the corresponding silent-with-banner
   notifications.
3. Tap any of them → app foregrounds into the active-ride card with
   the correct state.

### E3 — Cold-start from push
1. Force-quit the customer app.
2. Backend (via admin) sends a manual "ride.cancelled" test push to
   the user.
3. Tap the notification — the app launches directly into the rides
   history page, not the home page.

### E4 — Notification permission denied
1. Block notifications for the driver app at the OS level.
2. With the app in the FOREGROUND, dispatch a ride.
3. Expect the in-app offer modal still pops (FCM data path), even
   without a system notification.

---

## F. Realtime + reconnect

### F1 — WS happy path
1. Customer on the active-ride page.
2. Driver sends an ETA tick.
3. Customer sees the ETA pill update within 2 s.

### F2 — Network drop & recover
1. Customer on active-ride page.
2. Toggle airplane mode for 30 s.
3. Re-enable.
4. The status pill briefly shows "Reconnecting…" and the latest ride
   state syncs within 10 s. No duplicate UI cards, no stuck
   "Loading…".

### F3 — Long disconnect (5 min)
1. As F2 but leave airplane mode on for 5 min.
2. On reconnect, the app should land on whatever the current ride
   state truly is (which may be `completed` or `cancelled` while
   they were offline).

### F4 — Polling fallback
1. On the customer app, set the env override
   `REALTIME_DRIVER=polling` via `--dart-define` (Phase 2.0
   reconnect-scheduler test).
2. Run a full happy-path ride.
3. Confirm the active-ride card still updates (poll interval 5 s).

---

## G. Cancellation flows (regression net)

| #  | Cancelled by | Ride state at cancel       | Expected counterparty UI            |
|----|--------------|-----------------------------|--------------------------------------|
| G1 | Customer     | Requested (still searching) | Driver: never saw it — silent       |
| G2 | Customer     | Offered to a driver         | Driver: "Customer cancelled" toast  |
| G3 | Customer     | Accepted                    | Driver: same toast + return online  |
| G4 | Customer     | Driver arriving / arrived   | Driver: same                         |
| G5 | Customer     | On trip                     | NOT ALLOWED — button hidden          |
| G6 | Driver       | Accepted                    | Customer: requeue + "rematching"     |
| G7 | Driver       | Driver arriving             | Customer: requeue + "rematching"     |
| G8 | Driver       | On trip                     | Customer: requeue + "rematching"     |
| G9 | System       | Offer expired (no response) | Customer: silent requeue             |

---

## H. Admin live monitoring

### H1 — Live map updates
1. Open `/admin → Live monitor`.
2. Start a ride on the test devices.
3. Driver dot appears on the live map within 5 s of going online and
   updates throughout the trip.

### H2 — Force-cancel from admin
1. With a ride in `accepted` state, hit "Force cancel" on the
   Filament action menu.
2. Driver + customer apps both show the cancelled state within 5 s.

### H3 — Filter + drill-down
1. Filter the live rides list by status = `on_trip`.
2. Click a row.
3. Detail page shows the timeline, driver, vehicle, and
   fare breakdown.

---

## I. Edge cases

### I1 — Battery saver mode (Android)
1. Enable Battery Saver on the driver phone.
2. Start a ride.
3. GPS pump must continue (we hold the FOREGROUND_SERVICE_LOCATION
   permission). Customer should see no degradation.

### I2 — Doze mode (Android)
1. Driver phone idle, screen off, sitting on a desk.
2. After 30 min, dispatch a test offer.
3. The push must wake the phone (we send with `priority: high`).

### I3 — App Standby buckets (Android)
1. Force the customer app into the "rare" bucket:
   `adb shell am set-standby-bucket app.hangover.customer rare`.
2. Send a status-change push.
3. The push should still deliver — high priority bypasses standby.

### I4 — Storage full
1. Fill the device to < 50 MB free.
2. Launch the app.
3. Expect a clean error state, no Flutter framework crash.

### I5 — Wrong system clock
1. Set the device clock 2 hours ahead.
2. Try to log in.
3. The Sanctum token validation should still work (server-side
   clock), but JWT-based features (rare here) may complain — confirm
   error messages are intelligible.

---

## Reporting

Per scenario, file:

- `PASS` / `FAIL` + scenario id
- Device model + OS version
- App version (`Settings → About → Build`)
- Steps to reproduce (only if FAIL)
- Crash logs / Sentry event link
- Video if behaviour is visual / animation-related

Use the GitHub issue template `qa-finding.md` (lives in
`.github/ISSUE_TEMPLATE/`). Tag with `qa-2.1`.
