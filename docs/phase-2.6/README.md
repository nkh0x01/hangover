# Phase 2.6 — Real Mobile APK / App Build

This phase produces **installable debug APKs** of both the customer
app and the driver app, pre-wired to the cPanel staging backend at
[`https://ride.365sakartvelo.com`](https://ride.365sakartvelo.com)
with the magic OTP **`111111`**. Tester can sideload the `.apk` on a
real Android phone and exercise the full sign-in + ride flow.

Web preview is no longer the focus — Phase 2.6 is mobile-only.

## TL;DR

```bash
# One time on a fresh Mac
./mobile/scripts/check-mac-setup.sh        # tells you what's missing
# (install missing toolchain — see mac-android-setup.md)

# Every build
./mobile/scripts/build-debug-apk.sh         # both apps
# → build/customer-staging-debug.apk
# → build/driver-staging-debug.apk

# Push to phone
./mobile/scripts/build-debug-apk.sh both --install
# or
adb install -r build/customer-staging-debug.apk
```

## Index

| Doc | What it covers |
|---|---|
| [`mac-android-setup.md`](mac-android-setup.md) | Step-by-step Android Studio + SDK + `ANDROID_HOME` setup on a Mac that has none of it yet |
| [`install-on-phone.md`](install-on-phone.md) | Three ways to sideload the APK + the "install from unknown sources" walkthrough |
| [`staging-test-checklist.md`](staging-test-checklist.md) | What to verify on the phone end-to-end against the cPanel backend |
| [`troubleshooting.md`](troubleshooting.md) | Common failures (SDK missing, Gradle errors, API connection, maps key) and the exact fix |

## What Phase 2.6 changed

### Mobile
- `templates/android/<app>/app/build.gradle.kts` — the
  `com.google.gms.google-services` plugin is now applied **only when
  `google-services.json` exists**. Firebase dependencies are likewise
  guarded. Result: a fresh Mac can build and install the APK with no
  Firebase project. Push notifications fall back to `NullPushService`.
- `apps/{customer,driver}_app/lib/features/auth/presentation/phone_page.dart`
  — the "Preview app (no backend)" button now appears in both **dev
  and staging** flavors (gate is `!env.isProd`). Demo mode lives
  alongside the real backend in the same APK.
- `apps/{customer,driver}_app/lib/features/demo/state/demo_mode_controller.dart`
  — `activate()` accepts any non-prod flavor.

### Build / Infra
- `mobile/scripts/build-debug-apk.sh` (new) — one-shot script that
  builds the staging-flavor **debug** APK for either app or both,
  with optional `--install` to push to a connected device.
- `mobile/scripts/check-mac-setup.sh` (new) — verifies Flutter, Java,
  Android SDK, `ANDROID_HOME`, adb, sdkmanager, and `melos`. Prints
  the exact fix command for each missing piece. Exits non-zero on
  any required tool absent.

### Backend
- **No changes**. Phase 2.6 deliberately doesn't touch the cPanel
  backend or its config. The staging OTP escape hatch
  (`AUTH_STAGING_OTP=111111` + `APP_ENV != production`) and the
  health endpoint are reused exactly as Phase 2.5 left them.

## Known limitations

| Area | Limitation | Mitigation |
|---|---|---|
| **Realtime** | Reverb/Redis still disabled on cPanel. The driver/customer apps poll for ride status every 2 s. | Acceptable for QA; a real WebSocket needs a Linux VPS. |
| **Push notifications** | FCM isn't wired in this build (no `google-services.json`). Ride offers won't show as a notification when the app is backgrounded. | Demo mode + foreground polling cover the visual QA. Firebase wiring is a separate ops task. |
| **Maps** | If `MAPS_API_KEY` isn't exported when you build, the APK ships with the FallbackMapProvider (green grid + pins). | Same as Phase 2.5 — set `export MAPS_API_KEY=…` before `build-debug-apk.sh` to get real Google tiles. |
| **Background location** | Driver app requests `ACCESS_BACKGROUND_LOCATION` so the in-trip GPS stream survives a screen-off. There's no foreground-service implementation yet; on Android 14+ background GPS will drop after ~5 min when the screen is locked. | Acceptable for QA — keep the screen on for in-trip testing. |
| **Code signing** | Debug APKs are signed with the Flutter debug keystore. They install on any phone but won't update an existing release build of the same `applicationId`. | The staging build's package id ends in `.staging.debug`, so it lives side-by-side with the eventual release. |
