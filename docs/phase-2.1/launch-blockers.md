# Hangover Platform — Launch Blocker Register

> Phase 2.1 deliverable. Live list of every issue that must be cleared
> before the customer + driver apps can leave private QA and go to
> public beta. Each entry has a severity (`P0` blocks launch, `P1`
> blocks GA, `P2` we ship without), owner, and target build.
>
> Sorted by severity → discovery date. Strike through with `~~`
> when resolved.

Build cut: `0.1.0+2025-05-13` (Phase 2.1 baseline).

## Legend

- **P0** — show-stopper. App cannot enter public beta with this open.
- **P1** — must be fixed before GA / Play-Store / TestFlight external.
- **P2** — known, accepted, fix scheduled for Phase 2.2 or later.

## P0 — show-stoppers

### LB-001 — `flutter create` has never run; `android/` + `ios/` not present
- **Discovered:** Phase 2.1 audit (this branch)
- **Component:** Build system
- **Symptom:** Cloning the repo and running `flutter build apk` fails
  immediately because both apps lack a platform host directory.
- **Mitigation in this PR:** `mobile/scripts/setup-mobile-platforms.sh`
  generates them on demand and overlays the production templates
  under `mobile/templates/`.
- **Owner:** Mobile platform lead.
- **Verification:** Run the script on a clean checkout; both APKs
  build with `./mobile/scripts/build-apk.sh dev`.

### LB-002 — Firebase project not configured for either app
- **Discovered:** Phase 2.1 audit
- **Component:** Push notifications
- **Symptom:** Driver app cannot receive ride-offer pushes when
  backgrounded; customer app cannot receive ride-status updates with
  the app killed.
- **Mitigation in this PR:**
  - `CommunicationServiceProvider` resolves `PushGateway` from
    `kreait/laravel-firebase` only when `PUSH_DRIVER=firebase` and the
    `Messaging` binding resolves — otherwise falls through to
    `NullPushGateway`, so the rest of dispatch is unaffected.
  - Mobile side: `FirebasePushService` falls back to `NullPushService`
    when `Firebase.apps.isEmpty` (no `google-services.json`).
  - Documented step-by-step in `build-apk-runbook.md` § "Firebase setup".
- **Owner:** DevOps + mobile.
- **Verification:** Drop `google-services.json` into each Android
  module; trigger a ride offer; tester's phone wakes with the
  high-priority push and the offer modal.

### LB-003 — Sentry not initialised on either client or backend
- **Discovered:** Phase 2.1 audit
- **Component:** Observability
- **Symptom:** Any uncaught Dart exception or PHP error is silently
  swallowed in production. We'd be flying blind during beta.
- **Mitigation in this PR:**
  - Backend: `sentry/sentry-laravel ^4.10` added to composer.json;
    `bootstrap/app.php` forwards non-domain exceptions to
    `\Sentry\captureException` when the SDK is installed + a DSN is
    configured. `config/sentry.php` ships with a phone-scrubbing
    `before_send` hook (`App\Support\Observability\SentryScrub`).
  - Mobile: `mobile/packages/core/lib/src/observability/crash_reporter.dart`
    + `pubspec.yaml` integration. `bootstrap.dart` wraps `runApp` via
    `CrashReporter.bootstrap`.
- **Owner:** SRE.
- **Verification:** Throw a deliberate exception in staging; event
  appears in Sentry within 60 s with the phone-scrubbed payload.

### LB-004 — Background location prompt is never requested on the driver
- **Discovered:** Phase 2.1 audit
- **Component:** Driver permissions
- **Symptom:** Driver iOS app silently loses GPS the moment the screen
  locks, breaking the active-ride lifecycle.
- **Mitigation in this PR:**
  - Manifest + Info.plist templates declare the necessary capabilities
    (`ACCESS_BACKGROUND_LOCATION`, `FOREGROUND_SERVICE_LOCATION`,
    iOS `UIBackgroundModes` includes `location`, full Usage descriptions).
- **Remaining work:** wire `permission_handler` to request the upgrade
  *after* the driver toggles "Online" (deferred prompt). Tracked under
  LB-009.

### LB-005 — APK signing keystore missing
- **Discovered:** Phase 2.1 audit
- **Component:** Release pipeline
- **Symptom:** Cannot upload to Play Store; every install is debug-signed.
- **Mitigation in this PR:**
  - `mobile/templates/android/shared/key.properties.example` documents
    the format.
  - `app/build.gradle.kts` falls back to debug signing when
    `key.properties` is absent, so the dev loop still works.
- **Remaining work:** Generate the prod keystore and store in 1Password.
  Tracked under LB-010.

## P1 — must clear before GA

### LB-006 — APNs key not generated, not uploaded to Firebase
- **Discovered:** Phase 2.1 audit
- **Component:** iOS push
- **Symptom:** Without the APNs key on Firebase, no FCM-routed push
  reaches an iOS device.
- **Owner:** Mobile platform lead.
- **Tracked in:** `ios-testflight-checklist.md` § "APNs key".

### LB-007 — App Store Connect listings not created
- **Discovered:** Phase 2.1 audit
- **Component:** iOS distribution
- **Symptom:** Even an internal TestFlight build can't be uploaded
  without an App Store Connect record.
- **Owner:** Product.
- **Tracked in:** `ios-testflight-checklist.md`.

### LB-008 — `firebase_options.dart` not generated for either app
- **Discovered:** Phase 2.1 audit
- **Component:** Firebase bootstrap
- **Symptom:** Even after dropping `google-services.json`, the apps
  need `Firebase.initializeApp(options: DefaultFirebaseOptions...)`
  before the messaging client works. The generated `firebase_options.dart`
  file is the canonical way.
- **Owner:** Mobile.
- **Resolution:** Run `flutterfire configure` after the Firebase
  project exists. Result is gitignored.

### LB-009 — Permission-request UX not wired
- **Discovered:** Phase 2.1 audit
- **Component:** Onboarding
- **Symptom:** Users see the OS permission prompts on first launch
  with no context. Apple has rejected apps over this exact pattern.
- **Resolution:** Add a pre-prompt screen in customer onboarding (one
  per permission), gate the `Permission.request` call behind a user
  tap. Driver-side background-location prompt fires only after the
  first "Go online" tap.
- **Tracked in:** Phase 2.2 board.

### LB-010 — Prod upload keystores absent for both apps
- **Discovered:** Phase 2.1 audit
- **Component:** Release pipeline
- **Resolution:** Generate two keystores (one per app), upload to the
  team password manager + CI secrets. Add to
  `.github/workflows/mobile-build.yml`.

### LB-011 — Play Console listings not created
- **Discovered:** Phase 2.1 audit
- **Component:** Android distribution
- **Resolution:** Create the four Play Console entries (customer
  internal, customer prod, driver internal, driver prod). Will be
  documented in `play-console-checklist.md` (Phase 2.2).

### LB-012 — App icons + adaptive icons are placeholder Flutter swatches
- **Discovered:** Phase 2.1 audit
- **Component:** Branding
- **Resolution:** Use `flutter_launcher_icons` package after the brand
  team delivers the 1024×1024 source asset. Templates already point
  at `@mipmap/ic_launcher`.

### LB-013 — Native splash screen still the Flutter blue
- **Discovered:** Phase 2.1 audit
- **Component:** Branding
- **Resolution:** Either replace the launch_background drawable with
  the brand mark (Phase 2.1 partial — colour-only) and use
  `flutter_native_splash` for the full crossfade.

### LB-014 — Backend: queue worker process not provisioned in staging
- **Discovered:** Phase 2.1 audit
- **Component:** Realtime
- **Symptom:** `OfferRideToNextDriver`, `ExpireRideOffer`, and the new
  `SendOfferPush` listener are queued onto the `realtime` queue. If
  no worker is consuming it, dispatch stalls 5 s into a ride.
- **Resolution:** Horizon supervisor config already declares the
  `realtime` queue. SRE to confirm the `php artisan horizon` daemon is
  actually running on the staging app server.

### LB-015 — Phone-call deep link not handled in customer app
- **Discovered:** Phase 1.6 v2 follow-up
- **Component:** Customer driver-card
- **Symptom:** "Call driver" CTA opens dialer but doesn't pre-fill the
  number consistently across iOS / Android.
- **Resolution:** Use `url_launcher` with `tel:` and verify on every
  device tier in the QA matrix.

### LB-016 — Live monitor map performance with > 200 drivers
- **Discovered:** internal load test
- **Component:** Filament admin
- **Symptom:** Initial render of the live map locks the browser tab
  for ~3 s when many drivers are online.
- **Resolution:** Switch to MapLibre marker clustering (Phase 2.2).

## P2 — accepted technical debt

### LB-020 — Static screenshots are HTML/wkhtmltoimage renders
- Tracked since the Phase 1.6 screenshot work. Replace with real
  `flutter integration_test` goldens once Flutter SDK is available
  in CI (Phase 2.2).

### LB-021 — `referral_code` exposes user position via tail-of-ULID
- The last 8 chars of a Crockford ULID are random but short. Brute
  force is feasible at scale. Move to a separate `referral_codes`
  table with rate-limited issuance (Phase 3).

### LB-022 — Twilio not provisioned; SMS uses `NullSmsGateway` in staging
- Acceptable for QA (OTP is `000000` on staging) but blocks any
  external tester who's not on the team allow-list.

### LB-023 — Apple Sign-In not wired (PROVIDER stub only)
- `socialiteproviders/apple` is in composer.json but no controller is
  wired. App Store reviews require it when "Sign in with Google /
  Facebook" is offered — neither is offered today, so it's not
  blocking right now.

### LB-024 — Driver vehicle photo upload is stubbed
- The Filament admin page accepts uploads but the mobile app doesn't.
  Driver onboarding is currently 100 % through admin.

### LB-025 — No Crashlytics on Android
- We rely on Sentry. Crashlytics is redundant at this scale; revisit
  if Sentry's mobile cost becomes a concern.

## Resolved

(Empty — first revision.)
