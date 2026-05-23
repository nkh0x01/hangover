#!/usr/bin/env bash
set -euo pipefail

fail() {
  printf '\nERROR: %s\n' "$*" >&2
  exit 1
}

log() {
  printf '\n==> %s\n' "$*"
}

ok() {
  printf '[ok] %s\n' "$*"
}

warn() {
  printf '[warn] %s\n' "$*" >&2
}

PHASE="${1:-}"
ROLE="${2:-}"
APP_DIR="${3:-}"

case "$ROLE" in
  customer)
    BUNDLE_ID="app.ride360.customer"
    APP_NAME="Ride 360"
    ;;
  driver)
    BUNDLE_ID="app.ride360.driver"
    APP_NAME="Ride 360 Driver"
    ;;
  *)
    fail "Role must be customer or driver"
    ;;
esac

[[ -n "$APP_DIR" && -d "$APP_DIR" ]] || fail "App directory not found: ${APP_DIR:-<empty>}"

IOS_DIR="$APP_DIR/ios"
INFO_PLIST="$IOS_DIR/Runner/Info.plist"
[[ -f "$INFO_PLIST" ]] || fail "Info.plist not found: $INFO_PLIST"

REPO_ROOT="${CI_PRIMARY_REPOSITORY_PATH:-}"
if [[ -z "$REPO_ROOT" ]]; then
  REPO_ROOT="$(git -C "$APP_DIR" rev-parse --show-toplevel 2>/dev/null || cd "$APP_DIR/../../.." && pwd)"
fi

TEAM_ID_VALUE="${APPLE_TEAM_ID:-5BB9G38XX8}"
[[ "$TEAM_ID_VALUE" == "5BB9G38XX8" ]] ||
  fail "APPLE_TEAM_ID must be 5BB9G38XX8 for Ride 360 iOS release builds"

API_BASE_URL_VALUE="${API_BASE_URL:-https://ride.365sakartvelo.com}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/api/v1}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/}"
[[ "$API_BASE_URL_VALUE" == "https://ride.365sakartvelo.com" ]] ||
  fail "API_BASE_URL must be https://ride.365sakartvelo.com, got '$API_BASE_URL_VALUE'"

RIDE360_RELEASE_BUILD_VALUE="${RIDE360_RELEASE_BUILD:-true}"
DEV_BYPASS_ENABLED_VALUE="${DEV_BYPASS_ENABLED:-false}"
[[ "$RIDE360_RELEASE_BUILD_VALUE" == "true" ]] ||
  fail "RIDE360_RELEASE_BUILD must be true for production iOS builds"
[[ "$DEV_BYPASS_ENABLED_VALUE" == "false" ]] ||
  fail "DEV_BYPASS_ENABLED must be false for production iOS builds"

MAPS_API_KEY_VALUE="${IOS_MAPS_API_KEY:-${MAPS_API_KEY:-${GOOGLE_MAPS_API_KEY:-}}}"
[[ -n "$MAPS_API_KEY_VALUE" ]] ||
  fail "IOS_MAPS_API_KEY is required in Xcode Cloud. The Google Maps iOS key must allow bundle id $BUNDLE_ID."
[[ "$MAPS_API_KEY_VALUE" != "\$(MAPS_API_KEY)" ]] ||
  fail "IOS_MAPS_API_KEY still contains the Info.plist placeholder"

version_line="$(awk '$1 == "version:" { print $2; exit }' "$APP_DIR/pubspec.yaml")"
[[ -n "$version_line" ]] || fail "Could not read version from $APP_DIR/pubspec.yaml"
default_version_name="${version_line%%+*}"
if [[ "$version_line" == *"+"* ]]; then
  default_build_number="${version_line##*+}"
else
  default_build_number="1"
fi

VERSION_NAME_VALUE="${IOS_VERSION_NAME:-$default_version_name}"
BUILD_NUMBER_VALUE="${IOS_BUILD_NUMBER:-${CI_BUILD_NUMBER:-$default_build_number}}"
case "$BUILD_NUMBER_VALUE" in
  ''|*[!0-9]*) fail "Build number must be a positive integer, got '$BUILD_NUMBER_VALUE'" ;;
esac

BUILD_TIMESTAMP="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
GIT_COMMIT="$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo unknown)"
FLUTTER_CHANNEL_OR_VERSION="${FLUTTER_VERSION:-stable}"
FLUTTER_HOME="${FLUTTER_HOME:-$HOME/flutter}"

export PATH="$FLUTTER_HOME/bin:$FLUTTER_HOME/bin/cache/dart-sdk/bin:$HOME/.pub-cache/bin:$PATH"

ensure_flutter() {
  if [[ ! -x "$FLUTTER_HOME/bin/flutter" ]]; then
    log "Install Flutter $FLUTTER_CHANNEL_OR_VERSION"
    rm -rf "$FLUTTER_HOME"
    git clone --depth 1 --branch "$FLUTTER_CHANNEL_OR_VERSION" https://github.com/flutter/flutter.git "$FLUTTER_HOME"
  fi

  flutter --version
  flutter config --no-analytics
  flutter precache --ios
}

ensure_cocoapods() {
  if command -v pod >/dev/null 2>&1; then
    return
  fi

  if command -v brew >/dev/null 2>&1; then
    log "Install CocoaPods with Homebrew"
    brew install cocoapods
  else
    log "Install CocoaPods as a user gem"
    gem install --user-install cocoapods
    export PATH="$HOME/.gem/ruby/$(ruby -e 'print RUBY_VERSION[/\d+\.\d+/]')/bin:$PATH"
  fi
}

inject_maps_key() {
  log "Inject iOS Google Maps key for $BUNDLE_ID"
  /usr/libexec/PlistBuddy -c "Set :GMSApiKey $MAPS_API_KEY_VALUE" "$INFO_PLIST" 2>/dev/null ||
    /usr/libexec/PlistBuddy -c "Add :GMSApiKey string $MAPS_API_KEY_VALUE" "$INFO_PLIST"
}

configure_flutter_release() {
  log "Configure Flutter production build for $ROLE"
  cd "$APP_DIR"
  flutter pub get

  flutter build ios --release --config-only \
    --target lib/main_prod.dart \
    --build-name "$VERSION_NAME_VALUE" \
    --build-number "$BUILD_NUMBER_VALUE" \
    --dart-define="APP_ENV=production" \
    --dart-define="RIDE360_RELEASE_BUILD=$RIDE360_RELEASE_BUILD_VALUE" \
    --dart-define="DEV_BYPASS_ENABLED=$DEV_BYPASS_ENABLED_VALUE" \
    --dart-define="API_BASE_URL=$API_BASE_URL_VALUE" \
    --dart-define="WS_URL=wss://ride.365sakartvelo.com" \
    --dart-define="MAPS_API_KEY=$MAPS_API_KEY_VALUE" \
    --dart-define="HANGOVER_BUILD_APP_NAME=$APP_NAME" \
    --dart-define="HANGOVER_BUILD_VERSION_NAME=$VERSION_NAME_VALUE" \
    --dart-define="HANGOVER_BUILD_VERSION_CODE=$BUILD_NUMBER_VALUE" \
    --dart-define="HANGOVER_BUILD_TIMESTAMP=$BUILD_TIMESTAMP" \
    --dart-define="HANGOVER_BUILD_PACKAGE_ID=$BUNDLE_ID" \
    --dart-define="HANGOVER_BUILD_COMMIT=$GIT_COMMIT" \
    --dart-define="WS_KEY=${WS_KEY:-}" \
    --dart-define="SENTRY_DSN=${SENTRY_DSN:-}"
}

install_pods() {
  log "Install CocoaPods dependencies"
  cd "$APP_DIR"
  pod install --project-directory=ios
}

verify_generated_config() {
  generated_xcconfig="$IOS_DIR/Flutter/Generated.xcconfig"
  [[ -f "$generated_xcconfig" ]] || fail "Generated.xcconfig missing: $generated_xcconfig"
  grep -Fxq 'FLUTTER_TARGET=lib/main_prod.dart' "$generated_xcconfig" ||
    fail "Generated.xcconfig is not configured for lib/main_prod.dart"
  grep -Fxq "FLUTTER_BUILD_NAME=$VERSION_NAME_VALUE" "$generated_xcconfig" ||
    fail "Generated.xcconfig version name mismatch"
  grep -Fxq "FLUTTER_BUILD_NUMBER=$BUILD_NUMBER_VALUE" "$generated_xcconfig" ||
    fail "Generated.xcconfig build number mismatch"
}

verify_archive_if_present() {
  archive_path="${CI_ARCHIVE_PATH:-}"
  if [[ -z "$archive_path" || ! -d "$archive_path" ]]; then
    warn "CI_ARCHIVE_PATH is not available; skipping post-archive verification"
    return
  fi

  app_path="$(find "$archive_path/Products/Applications" -name '*.app' -type d -print | awk 'NR == 1 { print; exit }')"
  if [[ -z "${app_path:-}" || ! -d "$app_path" ]]; then
    warn "No .app bundle found inside archive; skipping post-archive verification"
    return
  fi

  app_plist="$app_path/Info.plist"
  actual_bundle_id="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleIdentifier' "$app_plist" 2>/dev/null || true)"
  [[ "$actual_bundle_id" == "$BUNDLE_ID" ]] ||
    fail "Archive bundle id '$actual_bundle_id' != expected '$BUNDLE_ID'"

  strings_file="$(mktemp)"
  find "$app_path" -type f -print0 | xargs -0 strings 2>/dev/null > "$strings_file" || true
  forbidden_pattern='10\.0\.2\.2|127\.0\.0\.1:8000|localhost:8000|api\.hangover|api\.staging|https://ride\.365sakartvelo\.com/api/v1|http://ride\.365sakartvelo\.com'
  if grep -Eq "$forbidden_pattern" "$strings_file"; then
    grep -En "$forbidden_pattern" "$strings_file" >&2 || true
    rm -f "$strings_file"
    fail "Archive contains a forbidden non-production API string"
  fi
  rm -f "$strings_file"
  ok "archive bundle id verified: $actual_bundle_id"
}

case "$PHASE" in
  post_clone)
    log "Xcode Cloud post-clone setup for Ride 360 $ROLE"
    ensure_flutter
    ensure_cocoapods
    inject_maps_key
    configure_flutter_release
    install_pods
    ;;
  pre_xcodebuild)
    log "Xcode Cloud pre-xcodebuild production guard for Ride 360 $ROLE"
    ensure_flutter
    ensure_cocoapods
    inject_maps_key
    configure_flutter_release
    install_pods
    verify_generated_config
    ok "ready for Xcode Cloud archive: $APP_NAME / $BUNDLE_ID / $API_BASE_URL_VALUE"
    ;;
  post_xcodebuild)
    log "Xcode Cloud post-xcodebuild verification for Ride 360 $ROLE"
    verify_archive_if_present
    ;;
  *)
    fail "Usage: $0 post_clone|pre_xcodebuild|post_xcodebuild customer|driver /path/to/app"
    ;;
esac
