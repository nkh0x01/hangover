# Install on a real Android phone

`./mobile/scripts/build-debug-apk.sh` produces two files in the
repo's `build/` directory:

```
build/customer-staging-debug.apk     (~40 MB)
build/driver-staging-debug.apk       (~40 MB)
```

These are debug-signed APKs that will install on any Android 6.0+
(API 23+) phone. Three ways to get them onto the device.

## Method A — adb over USB (fastest, recommended)

### One-time on the phone
1. Settings → About phone → **tap "Build number" 7 times** → developer
   mode enabled.
2. Settings → System → Developer options → **USB debugging** ON.
3. Plug the phone into the Mac with a USB cable that supports data
   (not all do — try a different one if `adb devices` is empty).
4. On the phone, tap **Allow** when the "Allow USB debugging?" prompt
   appears. Check **Always allow** for the laptop fingerprint.

### Verify the device is visible
```bash
adb devices
# Should print something like:
# List of devices attached
# RZ8N40DGJZN     device
```

### Install
```bash
adb install -r build/customer-staging-debug.apk
adb install -r build/driver-staging-debug.apk
```

`-r` = reinstall over an existing copy (preserves data + bypasses the
"app already exists" prompt). Add `-d` if you're downgrading
versionCode.

Or build + install in one shot:

```bash
./mobile/scripts/build-debug-apk.sh both --install
```

## Method B — share the .apk file (no USB cable needed)

1. From the Mac, send the `.apk` to the phone over **AirDrop** (not
   reliably supported by non-Mac phones — use Telegram, Drive, email,
   Slack DM, or a download URL).
2. On the phone, tap the downloaded file.
3. Android will say **"For your security, your phone isn't allowed
   to install unknown apps from this source"**. Tap **Settings** in
   the prompt.
4. Toggle **Allow from this source** ON for the app that fetched the
   APK (e.g. Files, Chrome, Telegram).
5. Tap **Back**, then **Install**.
6. The first install of a debug-signed APK shows a yellow
   **"Play Protect doesn't recognize this app's developer"**
   warning. Tap **Install anyway**.

After install, you'll find two new icons on the launcher:

- **Hangover Stg** (customer)
- **Hangover Driver Stg** (driver)

## Method C — Wireless adb (for tablets / no-USB-cable workflow)

Android 11+ supports adb over Wi-Fi without a cable.

```bash
# On the phone: Developer options → Wireless debugging → ON.
# Tap "Pair device with pairing code". Note the 6-digit code + the
# host:port shown.

adb pair <host>:<port>          # enter the code when prompted
adb connect <host>:<port>       # second port, shown on the same screen
adb devices                     # should now list the device

adb install -r build/customer-staging-debug.apk
```

## Uninstall later

```bash
adb uninstall app.hangover.customer.staging.debug
adb uninstall app.hangover.driver.staging.debug
```

Or long-press the icon on the home screen → drag to "Uninstall".

## Side-by-side with the release build

The package id of the staging-debug APK is
`app.hangover.customer.staging.debug`. A future production release
will be `app.hangover.customer`. Different package id → both can be
installed on the same phone without conflict. Same for the driver app.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `adb devices` empty | USB cable is charge-only / USB debugging not enabled | Switch cable; redo USB-debugging steps above |
| `INSTALL_FAILED_INSUFFICIENT_STORAGE` | Phone full | Free up space; debug APK is ~40 MB |
| `INSTALL_FAILED_VERSION_DOWNGRADE` | You're reinstalling an older versionCode over a newer one | `adb install -r -d ...` (the `-d` allows downgrade) |
| `INSTALL_FAILED_TEST_ONLY` | APK was built with `android:testOnly="true"` | Not the case for our debug builds; check you copied the right APK |
| App opens to a blank white screen and crashes | First-launch missing platform support (rare) | Check `adb logcat | grep flutter` for the real error |
| "App not installed" with no detail | Same `applicationId` already installed but signed by a different key | `adb uninstall app.hangover.customer.staging.debug` and retry |
