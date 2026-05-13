#!/usr/bin/env bash
#
# build-apk.sh
#
# Builds release APKs for one or both apps. Defaults to the `dev`
# flavor + debug signing so a developer can `adb install` immediately
# after cloning.
#
# Usage:
#   ./mobile/scripts/build-apk.sh                 # both apps, dev, debug
#   ./mobile/scripts/build-apk.sh prod customer   # customer prod release
#   ./mobile/scripts/build-apk.sh staging driver  # driver staging release
#
# Output:
#   build/customer-<flavor>.apk
#   build/driver-<flavor>.apk

set -euo pipefail

FLAVOR="${1:-dev}"
TARGET="${2:-all}"

case "$FLAVOR" in
  dev|staging|prod) ;;
  *)
    echo "Unknown flavor: $FLAVOR (expected dev|staging|prod)" >&2
    exit 1
    ;;
esac

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/../build"
mkdir -p "$OUT"

build_one() {
  local app="$1"
  local entry="lib/main_${FLAVOR}.dart"
  local cap="$(echo "${FLAVOR:0:1}" | tr '[:lower:]' '[:upper:]')${FLAVOR:1}"

  cd "$ROOT/apps/$app"

  if [[ ! -d "android" ]]; then
    echo "✖ $app/android/ not present — run ./mobile/scripts/setup-mobile-platforms.sh first" >&2
    exit 1
  fi

  if [[ ! -f "$entry" ]]; then
    echo "✖ $entry missing in $app" >&2
    exit 1
  fi

  echo
  echo "==> Building $app  ($FLAVOR)"
  flutter pub get >/dev/null
  flutter build apk \
    --release \
    --target="$entry" \
    --flavor="$FLAVOR" \
    --dart-define=FLAVOR="$FLAVOR" \
    --dart-define-from-file=../../config/$FLAVOR.json 2>/dev/null \
    || flutter build apk \
       --release \
       --target="$entry" \
       --flavor="$FLAVOR" \
       --dart-define=FLAVOR="$FLAVOR"

  local apk="build/app/outputs/flutter-apk/app-${FLAVOR}-release.apk"
  if [[ ! -f "$apk" ]]; then
    apk="build/app/outputs/apk/${FLAVOR}/release/app-${FLAVOR}-release.apk"
  fi

  cp "$apk" "$OUT/${app%_app}-${FLAVOR}.apk"
  echo "    → $OUT/${app%_app}-${FLAVOR}.apk"
}

case "$TARGET" in
  all)
    build_one customer_app
    build_one driver_app
    ;;
  customer)
    build_one customer_app
    ;;
  driver)
    build_one driver_app
    ;;
  *)
    echo "Unknown target: $TARGET (expected all|customer|driver)" >&2
    exit 1
    ;;
esac

echo
echo "✔ APKs ready in $OUT/"
ls -lh "$OUT"/*.apk 2>/dev/null || true
