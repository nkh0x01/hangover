# Mac → Android SDK setup

You're on a Mac. The Android SDK is missing. Follow this once and
`./mobile/scripts/check-mac-setup.sh` will go green.

Total time: ~30 minutes (most of it is the SDK download).

## 0. Sanity check first

```bash
./mobile/scripts/check-mac-setup.sh
```

It tells you exactly what's missing. The rest of this doc walks
through each fix.

---

## 1. Java 17

The Android Gradle Plugin pinned in this repo (AGP 8.7) requires
Java 17. Java 21 sometimes works, sometimes doesn't — stick with 17.

```bash
brew install --cask temurin@17
# Verify:
/usr/libexec/java_home -V              # lists installed JDKs
export JAVA_HOME="$(/usr/libexec/java_home -v 17)"
java -version                          # should print 17.x
```

Add the `JAVA_HOME` export to `~/.zshrc` so every new terminal picks
it up:

```bash
echo 'export JAVA_HOME="$(/usr/libexec/java_home -v 17)"' >> ~/.zshrc
```

---

## 2. Flutter

If you don't have it yet:

```bash
brew install --cask flutter
flutter --version                      # should print 3.27+
```

Or grab the official tarball from <https://flutter.dev/docs/get-started/install/macos>
and put it on PATH.

---

## 3. Android Studio + SDK

The easiest path is to install Android Studio — it bundles the SDK
manager, an AVD manager, and the platform tools (`adb`).

```bash
brew install --cask android-studio
```

**Open Android Studio once.** It'll offer to download the SDK on
first launch — accept all defaults. When you reach the welcome
screen:

1. Click **More Actions → SDK Manager** (or
   `⌘+,` → Languages & Frameworks → Android SDK).
2. In the **SDK Platforms** tab, check:
   - **Android 14 (API 34)** — minimum target for the Play Store.
3. In the **SDK Tools** tab, check:
   - **Android SDK Build-Tools 34.x**
   - **Android SDK Command-line Tools (latest)**
   - **Android SDK Platform-Tools** (gives you `adb`)
4. Click **Apply** and wait for the download.

Default install path is `~/Library/Android/sdk`.

---

## 4. Export `ANDROID_HOME` + PATH

Add these three lines to `~/.zshrc`:

```bash
export ANDROID_HOME="$HOME/Library/Android/sdk"
export PATH="$PATH:$ANDROID_HOME/platform-tools"
export PATH="$PATH:$ANDROID_HOME/cmdline-tools/latest/bin"
```

Then either open a new terminal or `source ~/.zshrc`. Verify:

```bash
adb --version
sdkmanager --version
```

---

## 5. Accept SDK licenses

Flutter complains if you skip this.

```bash
flutter doctor --android-licenses
# Press y at every prompt.
```

Then:

```bash
flutter doctor
```

You want **green checks** next to:
- Flutter (Channel stable, 3.27+)
- Android toolchain
- Connected device (only if a phone is plugged in)

`Xcode` and `Chrome` are nice-to-have but not required for Phase 2.6.

---

## 6. (Optional) Melos for workspace commands

```bash
dart pub global activate melos
echo 'export PATH="$PATH:$HOME/.pub-cache/bin"' >> ~/.zshrc
melos --version
```

You don't strictly need melos to build a single APK — Phase 2.6's
`build-debug-apk.sh` calls `flutter pub get` directly — but you'll
want it for `melos run analyze` / `melos run test`.

---

## 7. Re-run the check

```bash
./mobile/scripts/check-mac-setup.sh
```

Every line should be green. Now:

```bash
./mobile/scripts/build-debug-apk.sh
```

…builds both APKs in 5–10 minutes (longer on the first run, while
Gradle downloads the AGP + Kotlin + Firebase artifacts).

---

## (Alternative) Command-line-only SDK install

If you really don't want Android Studio, you can install just the
command-line tools:

```bash
brew install --cask android-commandlinetools
export ANDROID_HOME="$HOME/Library/Android/sdk"
mkdir -p "$ANDROID_HOME"
sdkmanager --sdk_root="$ANDROID_HOME" \
  "platform-tools" \
  "platforms;android-34" \
  "build-tools;34.0.0"
sdkmanager --sdk_root="$ANDROID_HOME" --licenses    # press y until done
```

Then export `ANDROID_HOME` / PATH and re-run the check script. This
path saves disk space (~600 MB vs ~3 GB) but you lose the GUI SDK
manager + AVD manager.
