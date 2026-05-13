#!/usr/bin/env bash
#
# setup-mobile-platforms.sh
#
# One-shot bootstrap for a developer cloning the repo on a Flutter-
# enabled machine. Generates the platform-specific (`android/`, `ios/`)
# directories that we don't commit, then overlays the production
# templates from `mobile/templates/`.
#
# Idempotent — running it twice will refresh the overlays in place.
#
# Usage:
#   ./mobile/scripts/setup-mobile-platforms.sh

set -euo pipefail

if ! command -v flutter >/dev/null 2>&1; then
  echo "✖ Flutter not on PATH. Install Flutter 3.24+ first." >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TEMPLATES="$ROOT/templates"

for app in customer_app driver_app; do
  APP_DIR="$ROOT/apps/$app"
  echo
  echo "==> Bootstrapping $app"
  cd "$APP_DIR"

  # 1. Generate android/ + ios/ if missing.
  if [[ ! -d "android" || ! -d "ios" ]]; then
    echo "    flutter create --platforms=android,ios ."
    flutter create --platforms=android,ios --org app.hangover .
  fi

  # 2. Overlay Android manifest + Gradle + resources.
  echo "    overlay: android/"
  cp "$TEMPLATES/android/$app/app/src/main/AndroidManifest.xml" \
     "android/app/src/main/AndroidManifest.xml"
  mkdir -p android/app/src/main/res/{xml,values,drawable}
  cp -r "$TEMPLATES/android/$app/app/src/main/res/." \
        "android/app/src/main/res/"
  cp "$TEMPLATES/android/$app/app/build.gradle.kts" \
     "android/app/build.gradle.kts"
  cp "$TEMPLATES/android/shared/proguard-rules.pro" \
     "android/app/proguard-rules.pro"
  cp "$TEMPLATES/android/shared/build.gradle.kts" \
     "android/build.gradle.kts"
  cp "$TEMPLATES/android/shared/settings.gradle.kts" \
     "android/settings.gradle.kts"
  cp "$TEMPLATES/android/shared/gradle.properties" \
     "android/gradle.properties"

  # 3. iOS: copy the Info.plist additions next to the user's Info.plist
  #    (they merge manually — Xcode plist edits don't survive overlay).
  if [[ -f "$TEMPLATES/ios/$app/Info.plist.additions" ]]; then
    echo "    overlay: ios/Runner/Info.plist.additions  (merge manually)"
    cp "$TEMPLATES/ios/$app/Info.plist.additions" \
       "ios/Runner/Info.plist.additions"
  fi
  if [[ -f "$TEMPLATES/ios/$app/Runner.entitlements" ]]; then
    cp "$TEMPLATES/ios/$app/Runner.entitlements" \
       "ios/Runner/Runner.entitlements"
  fi
  cp "$TEMPLATES/ios/shared/Podfile.additions" \
     "ios/Podfile.additions" 2>/dev/null || true

  # 4. Print a key.properties example if missing.
  if [[ ! -f "android/key.properties" ]]; then
    cp "$TEMPLATES/android/shared/key.properties.example" \
       "android/key.properties.example"
    echo "    note: android/key.properties not present — release builds will use the debug keystore."
    echo "          copy android/key.properties.example → android/key.properties and fill in the values to sign for release."
  fi

  # 5. flutter pub get
  echo "    flutter pub get"
  flutter pub get >/dev/null
done

echo
echo "✔ Both apps bootstrapped."
echo
echo "Next steps:"
echo "  1. Drop google-services.json into mobile/apps/{customer_app,driver_app}/android/app/"
echo "  2. Drop GoogleService-Info.plist into mobile/apps/{customer_app,driver_app}/ios/Runner/"
echo "  3. Merge the keys from ios/Runner/Info.plist.additions into ios/Runner/Info.plist"
echo "  4. Set MAPS_API_KEY in your env (or pass via --dart-define / Gradle property)"
echo "  5. ./mobile/scripts/build-apk.sh dev    # smoke build"
