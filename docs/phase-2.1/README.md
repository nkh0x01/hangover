# Phase 2.1 — Real Device QA + Launch Preparation

Foundation work that bridges the simulator-only Phase 2.0 to the first
installable APK on a tester's phone. Everything in this folder is
deliverable for Phase 2.1.

## Documents

| File                                              | Audience                  |
|---------------------------------------------------|---------------------------|
| [`launch-readiness-report.md`](launch-readiness-report.md) | Steering / sign-off    |
| [`build-apk-runbook.md`](build-apk-runbook.md)             | Mobile engineers        |
| [`ios-testflight-checklist.md`](ios-testflight-checklist.md) | Mobile + product       |
| [`device-qa-scenarios.md`](device-qa-scenarios.md)           | QA                      |
| [`launch-blockers.md`](launch-blockers.md)                   | Cross-team — live      |

## What changed in this phase

### Mobile
- `mobile/templates/android/` — production overlays for both apps
  (manifests, Gradle, network-security config, splash, icons, ProGuard).
- `mobile/templates/ios/` — Info.plist additions, entitlements, Podfile additions.
- `mobile/scripts/setup-mobile-platforms.sh` — bootstrap script.
- `mobile/scripts/build-apk.sh` — build script.
- `mobile/packages/core/lib/src/observability/crash_reporter.dart` — Sentry facade.
- `apps/{customer_app,driver_app}/lib/bootstrap.dart` — wired Sentry + PushService init.

### Backend
- `app/Modules/Communication/Contracts/PushGateway.php` + `PushResult.php`.
- `app/Modules/Communication/Push/{FirebasePushGateway,NullPushGateway}.php`.
- `app/Modules/Riding/Listeners/SendOfferPush.php` — bridges `RideOffered` → FCM.
- `app/Support/Observability/SentryScrub.php` — phone + token PII scrub.
- `config/{push,sentry}.php`, `config/logging.php` `push` channel.
- `bootstrap/app.php` — Sentry forwarder for uncaught exceptions.
- `tests/Feature/Riding/OfferPushNotificationTest.php` — 3 tests.

## What's blocking public beta

See [`launch-blockers.md`](launch-blockers.md). One P0 remains
(production keystore generation, ~1h ops task); 11 P1s are deferred to
Phase 2.2.
