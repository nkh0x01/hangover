#!/usr/bin/env bash
#
# build-debug-apk.sh — Phase 2.6
#
# One-shot debug-APK builder for QA on real Android phones. Always
# points at the staging cPanel backend at
# https://ride.365sakartvelo.com and uses the staging flavor's magic
# OTP (111111). Demo mode stays available inside the APK so QA can
# preview the flow without a backend round-trip.
#
# Differences vs. build-apk.sh (Phase 2.1 / 2.5):
#   - Builds the DEBUG buildType — installs without an upload keystore
#     and lives side-by-side with any release build already on the phone.
#   - Defaults to the STAGING flavor (vs. dev) so the APK talks to the
#     real cPanel backend out of the box.
#   - Auto-runs setup-mobile-platforms.sh if android/ is missing.
#   - Optional --install: pushes to whatever adb device is plugged in.
#
# Usage:
#   ./mobile/scripts/build-debug-apk.sh                 # both apps
#   ./mobile/scripts/build-debug-apk.sh customer        # customer only
#   ./mobile/scripts/build-debug-apk.sh driver          # driver only
#   ./mobile/scripts/build-debug-apk.sh both --install  # build + adb install
#
# Output:
#   build/customer-staging-debug.apk
#   build/driver-staging-debug.apk

set -euo pipefail

TARGET="${1:-both}"
INSTALL=0
for arg in "${@:2}"; do
  case "$arg" in
    --install|-i) INSTALL=1 ;;
    *) echo "Unknown flag: $arg" >&2; exit 1 ;;
  esac
done

if [[ -t 1 ]]; then
  G=$'\e[32m'; R=$'\e[31m'; Y=$'\e[33m'; D=$'\e[2m'; X=$'\e[0m'
else
  G='' R='' Y='' D='' X=''
fi

FLAVOR=staging
BUILD_TYPE=debug

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/../build"
mkdir -p "$OUT"

# Pre-flight: Flutter must be on PATH. Everything else (Android SDK,
# Java, adb) flutter itself complains about with a clearer message.
if ! command -v flutter >/dev/null 2>&1; then
  echo "${R}✖${X} flutter not on PATH. Run ./mobile/scripts/check-mac-setup.sh for guidance." >&2
  exit 1
fi

build_one() {
  local app="$1"
  local entry="lib/main_${FLAVOR}.dart"
  local app_dir="$ROOT/apps/$app"
  local out_apk="$OUT/${app%_app}-${FLAVOR}-${BUILD_TYPE}.apk"

  echo
  echo "${G}==>${X} Building ${app} (${FLAVOR} / ${BUILD_TYPE})"

  cd "$app_dir"

  # If the platform folder isn't there yet, run the bootstrap once. The
  # script itself is idempotent; running it twice just refreshes overlays.
  if [[ ! -d "android" ]]; then
    echo "    android/ missing — running setup-mobile-platforms.sh first"
    "$ROOT/scripts/setup-mobile-platforms.sh" >/dev/null
  fi

  if [[ ! -f "$entry" ]]; then
    echo "${R}✖${X} $entry missing in $app" >&2
    exit 1
  fi

  flutter pub get >/dev/null

  # Compose dart-defines from the committed JSON file + any extra
  # overrides from the shell env (MAPS_API_KEY most commonly).
  local defines=(
    --dart-define=FLAVOR="$FLAVOR"
  )
  if [[ -f "$ROOT/config/$FLAVOR.json" ]]; then
    defines+=(--dart-define-from-file="$ROOT/config/$FLAVOR.json")
  fi
  if [[ -n "${MAPS_API_KEY:-}" ]]; then
    defines+=(--dart-define=MAPS_API_KEY="$MAPS_API_KEY")
  fi

  # The actual build. --debug keeps it installable without an upload
  # keystore and includes the JIT-compatible profile data so QA can
  # see useful stack traces.
  flutter build apk \
    --debug \
    --target="$entry" \
    --flavor="$FLAVOR" \
    "${defines[@]}"

  # Output path lives under build/app/outputs/...; pick whichever shape
  # AGP produced.
  local src=""
  for cand in \
    "build/app/outputs/flutter-apk/app-${FLAVOR}-${BUILD_TYPE}.apk" \
    "build/app/outputs/apk/${FLAVOR}/${BUILD_TYPE}/app-${FLAVOR}-${BUILD_TYPE}.apk"; do
    if [[ -f "$cand" ]]; then src="$cand"; break; fi
  done
  if [[ -z "$src" ]]; then
    echo "${R}✖${X} could not locate built APK under $app_dir/build/app/outputs/" >&2
    exit 1
  fi

  cp "$src" "$out_apk"
  echo "    ${G}→${X} $out_apk  ($(du -h "$out_apk" | cut -f1))"

  if (( INSTALL )); then
    if ! command -v adb >/dev/null 2>&1; then
      echo "${Y}!${X} --install given but adb not on PATH; skipping" >&2
      return
    fi
    if ! adb devices | awk 'NR>1 && $2=="device" {n++} END {exit !n}'; then
      echo "${Y}!${X} no adb device attached; skipping install" >&2
      return
    fi
    echo "    adb install -r -d \"$out_apk\""
    adb install -r -d "$out_apk"
  fi
}

case "$TARGET" in
  both|all)
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
    echo "Unknown target: $TARGET (expected both|all|customer|driver)" >&2
    exit 1
    ;;
esac

echo
echo "${G}✔${X} APKs ready in $OUT/"
ls -lh "$OUT"/*.apk 2>/dev/null || true
echo
echo "Install on a phone:"
echo "  adb install -r build/<file>.apk"
echo
echo "Or send the .apk via AirDrop / email / Telegram and tap it on the phone."
echo "Settings → Apps → Special access → Install unknown apps → allow your file manager."
