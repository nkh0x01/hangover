# Phase 2.6 troubleshooting

Common failures while building or running the Android APKs. Each
entry has the symptom, the root cause, and the exact fix.

## Build-time

### `error: ANDROID_HOME is not set / Android SDK not found`

**Symptom:** `flutter build apk` prints a missing-SDK error and exits.

**Cause:** Android Studio not installed, OR the SDK is there but
`ANDROID_HOME` isn't exported.

**Fix:** Follow [`mac-android-setup.md`](mac-android-setup.md)
sections 3–4. Then `./mobile/scripts/check-mac-setup.sh`.

---

### `Could not resolve dev.flutter:flutter-gradle-plugin`

**Symptom:** Gradle sync fails on the first build with a
"Could not resolve" against a Flutter plugin coordinate.

**Cause:** `local.properties` is missing or points at the wrong
Flutter SDK path.

**Fix:**
```bash
cd mobile/apps/customer_app/android
echo "flutter.sdk=$(flutter --version --machine | grep -o '"flutterRoot":"[^"]*"' | cut -d'"' -f4)" >> local.properties
# Repeat for driver_app/android/
```

Or just delete `local.properties` and re-run `flutter pub get` from
the app directory — Flutter recreates it.

---

### `File google-services.json is missing.`

**Symptom:** Gradle errors with "File google-services.json is
missing" from the `processGoogleServices` task.

**Cause:** Stale Gradle plugin application — your checkout has the
old (pre-Phase 2.6) `build.gradle.kts` template that unconditionally
applies `com.google.gms.google-services`.

**Fix:** Re-run the setup script. It overlays the current Phase 2.6
templates that apply the plugin conditionally:

```bash
./mobile/scripts/setup-mobile-platforms.sh
```

If you've already wired up a real Firebase project, dropping the
real `google-services.json` into `mobile/apps/<app>/android/app/`
re-enables the plugin and FCM push.

---

### `Execution failed for task ':app:checkDebugAarMetadata'.`

**Symptom:** A library declares it needs `compileSdk >= 35` but
your toolchain only has 34.

**Cause:** A plugin bumped its minimum SDK in a recent release.

**Fix:** Open Android Studio → SDK Manager → SDK Platforms → check
**Android 15 (API 35)** → Apply. Then:

```bash
flutter clean
./mobile/scripts/build-debug-apk.sh
```

---

### `Daemon will be stopped at the end of the build after running out of JVM memory`

**Symptom:** Gradle prints OOM, build crawls or fails.

**Cause:** Default JVM heap too small for the Firebase + AGP graph.

**Fix:** Already handled — `gradle.properties` in the template sets
`-Xmx4G -XX:MaxMetaspaceSize=2G`. If you customised it, raise both.

---

### Build hangs at `> Configure project :app`

**Symptom:** First-run Gradle sits silent for minutes.

**Cause:** Gradle is downloading AGP, Kotlin, Firebase + the
build-tools. The first build pulls ~500 MB.

**Fix:** Be patient. Subsequent builds are 30 s warm. If it really
seems hung, `Ctrl+C`, then:

```bash
cd mobile/apps/customer_app/android
./gradlew --stop
./gradlew :app:assembleStagingDebug --info
```

The `--info` flag prints which artifact is being fetched.

---

## Runtime

### App opens, white screen, then closes

**Symptom:** Launching the APK shows a blank screen, app crashes
within a second, no error in the UI.

**Cause:** A pre-bootstrap exception. `Sentry / Firebase / Riverpod`
threw and the crash reporter swallowed it without surfacing.

**Fix:** Plug the phone in, then:

```bash
adb logcat -c                          # clear old logs
adb shell am force-stop app.hangover.customer.staging.debug
adb shell am start -n app.hangover.customer.staging.debug/app.hangover.customer.MainActivity
adb logcat | grep -E 'flutter|Hangover|FATAL'
```

Look for a Dart exception. Most common: `MissingPluginException`,
fixed by `cd mobile/apps/customer_app && flutter clean && flutter pub get && (re-build APK)`.

---

### `Connection refused` / `Network is unreachable`

**Symptom:** Tap **Send code** → red "Network error" banner.

**Causes (in order of likelihood):**
1. Phone has no internet (Wi-Fi off + cellular off).
2. Phone is on a captive-portal Wi-Fi that hasn't been signed into.
3. `https://ride.365sakartvelo.com` is actually down — verify with
   `./mobile/scripts/staging-smoke.sh` from the Mac.
4. The phone's clock is wildly wrong (TLS rejects the cert).

**Fix:** In order:
1. Toggle airplane mode off; verify Chrome can load
   `https://ride.365sakartvelo.com/api/v1/health`.
2. Sign into the captive portal.
3. Wait — staging is on cPanel shared hosting and goes down ~once
   a month for ~30 min.
4. Settings → System → Date & time → enable "Set automatically".

---

### Map shows the green grid even though I set `MAPS_API_KEY`

**Symptom:** You exported `MAPS_API_KEY` and rebuilt, but the home
screen still shows the FallbackMapProvider grid.

**Causes:**
1. `MAPS_API_KEY` wasn't actually in your shell env when you ran the
   build script. Verify with `echo $MAPS_API_KEY`.
2. The key is set but **not restricted to the right Android
   package + SHA-1**. Google Cloud Console quietly fails.
3. Build was cached. `flutter clean` first.

**Fix:**
```bash
export MAPS_API_KEY="AIza…"
flutter clean
./mobile/scripts/build-debug-apk.sh
adb install -r build/customer-staging-debug.apk
```

For #2, the debug build uses the **debug keystore** SHA-1 fingerprint,
which differs from your release keystore. Get it via:

```bash
keytool -list -v \
  -keystore ~/.android/debug.keystore \
  -storepass android -keypass android \
  -alias androiddebugkey | grep SHA1
```

Add that SHA-1 + the package id
`app.hangover.customer.staging.debug` to the Android application
restriction on the maps key in Google Cloud.

---

### Real Google tiles render but with a red "For development purposes
only" watermark

**Cause:** The Maps key has the wrong package restriction (or none),
so Google flags every tile request as untrusted.

**Fix:** Restrict the key in Google Cloud Console → APIs &
Services → Credentials → edit your key →
**Application restrictions: Android apps** → add:
- `app.hangover.customer.staging.debug` + debug SHA-1
- `app.hangover.customer.staging` + release SHA-1
- `app.hangover.driver.staging.debug` + debug SHA-1
- `app.hangover.driver.staging` + release SHA-1

---

### OTP banner shows but `Verify` returns "Code does not match."

**Cause:** Server-side `AUTH_STAGING_OTP` config cache is stale, or
the env var got reset on a deploy.

**Fix:** SSH to the cPanel host:

```bash
cd ~/ride.365sakartvelo.com/current
php artisan config:clear && php artisan config:cache
grep AUTH_STAGING_OTP shared/.env       # should show '111111'
```

---

### `OtpThrottledException` / 423 Locked

**Cause:** Per-phone-number 60-second cooldown.

**Fix:** Wait, or use a different phone number suffix. Each `+995555000XXX`
counts as a separate phone for throttling purposes.

---

### Demo "Preview app" button is missing

**Cause:** You're on a prod-flavor build. The button is intentionally
hidden in prod.

**Fix:** Rebuild with the staging flavor:

```bash
./mobile/scripts/build-debug-apk.sh
```

(The script defaults to staging — only an explicit `--target=lib/main_prod.dart`
override changes that.)

---

### `RebootRequiredError` / "An update is in progress" while installing

**Symptom:** `adb install` returns
`INSTALL_FAILED_UPDATE_INCOMPATIBLE` or similar.

**Cause:** A previous install left the package in a "scanning" state,
or you're trying to install over an APK signed by a different key.

**Fix:**
```bash
adb uninstall app.hangover.customer.staging.debug
adb install -r build/customer-staging-debug.apk
```

---

## When all else fails

Capture a full reproducer:

```bash
adb logcat -c
flutter clean
./mobile/scripts/build-debug-apk.sh customer 2>&1 | tee /tmp/build.log
adb install -r build/customer-staging-debug.apk 2>&1 | tee -a /tmp/build.log
# launch the app, reproduce the issue
adb logcat | tee /tmp/runtime.log
```

Share `/tmp/build.log` + `/tmp/runtime.log` with engineering.
