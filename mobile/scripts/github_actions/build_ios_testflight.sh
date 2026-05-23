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

ROLE="${1:-}"
case "$ROLE" in
  customer)
    APP="customer_app"
    BUNDLE_ID="app.ride360.customer"
    APP_NAME="Ride360-Customer"
    DISPLAY_NAME="Ride 360"
    ;;
  driver)
    APP="driver_app"
    BUNDLE_ID="app.ride360.driver"
    APP_NAME="Ride360-Driver"
    DISPLAY_NAME="Ride 360 Driver"
    ;;
  *)
    fail "Usage: $0 customer|driver"
    ;;
esac

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
APP_DIR="$REPO_ROOT/mobile/apps/$APP"
IOS_DIR="$APP_DIR/ios"
INFO_PLIST="$IOS_DIR/Runner/Info.plist"
ARTIFACT_DIR="$REPO_ROOT/build/github-actions-ios/$ROLE"
ARCHIVE_PATH="$ARTIFACT_DIR/$APP_NAME.xcarchive"
EXPORT_PATH="$ARTIFACT_DIR/export"
EXPORT_OPTIONS="$ARTIFACT_DIR/ExportOptions.plist"
ASC_PRIVATE_KEYS_DIR="$HOME/.appstoreconnect/private_keys"

EXPECTED_ISSUER_ID="c31a4d8b-f081-4263-be24-796d00558ddd"
EXPECTED_KEY_ID="VWBCPRZ4JS"
API_BASE_URL_VALUE="${API_BASE_URL:-https://ride.365sakartvelo.com}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/api/v1}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/}"

[[ -d "$APP_DIR" ]] || fail "App directory not found: $APP_DIR"
[[ -f "$INFO_PLIST" ]] || fail "Info.plist not found: $INFO_PLIST"
[[ "$API_BASE_URL_VALUE" == "https://ride.365sakartvelo.com" ]] ||
  fail "API_BASE_URL must be https://ride.365sakartvelo.com, got '$API_BASE_URL_VALUE'"
[[ "${RIDE360_RELEASE_BUILD:-true}" == "true" ]] ||
  fail "RIDE360_RELEASE_BUILD must be true"
[[ "${DEV_BYPASS_ENABLED:-false}" == "false" ]] ||
  fail "DEV_BYPASS_ENABLED must be false"
[[ "${APPLE_TEAM_ID:-}" == "5BB9G38XX8" ]] ||
  fail "APPLE_TEAM_ID must be set to 5BB9G38XX8"
[[ "${APP_STORE_CONNECT_ISSUER_ID:-}" == "$EXPECTED_ISSUER_ID" ]] ||
  fail "APP_STORE_CONNECT_ISSUER_ID must be $EXPECTED_ISSUER_ID"
[[ "${APP_STORE_CONNECT_KEY_ID:-}" == "$EXPECTED_KEY_ID" ]] ||
  fail "APP_STORE_CONNECT_KEY_ID must be $EXPECTED_KEY_ID"
[[ -n "${APP_STORE_CONNECT_PRIVATE_KEY:-}" ]] ||
  fail "APP_STORE_CONNECT_PRIVATE_KEY GitHub secret is required"
[[ -n "${IOS_MAPS_API_KEY:-}" ]] ||
  fail "IOS_MAPS_API_KEY GitHub secret is required for $BUNDLE_ID"

version_line="$(awk '$1 == "version:" { print $2; exit }' "$APP_DIR/pubspec.yaml")"
[[ -n "$version_line" ]] || fail "Could not read version from $APP_DIR/pubspec.yaml"
default_version_name="${version_line%%+*}"
if [[ "$version_line" == *"+"* ]]; then
  default_build_number="${version_line##*+}"
else
  default_build_number="1"
fi

VERSION_NAME_VALUE="${IOS_VERSION_NAME:-$default_version_name}"
BUILD_NUMBER_VALUE="${IOS_BUILD_NUMBER:-${GITHUB_RUN_NUMBER:-$default_build_number}}"
case "$BUILD_NUMBER_VALUE" in
  ''|*[!0-9]*) fail "Build number must be a positive integer, got '$BUILD_NUMBER_VALUE'" ;;
esac

BUILD_TIMESTAMP="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
GIT_COMMIT="$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo unknown)"

prepare_app_store_connect_key() {
  log "Prepare App Store Connect API key"
  mkdir -p "$ASC_PRIVATE_KEYS_DIR"
  ASC_KEY_PATH="$ASC_PRIVATE_KEYS_DIR/AuthKey_${APP_STORE_CONNECT_KEY_ID}.p8"
  export ASC_KEY_PATH
  python3 - <<'PY'
import os
from pathlib import Path

key = os.environ["APP_STORE_CONNECT_PRIVATE_KEY"].strip().replace("\\n", "\n")
if not key.endswith("\n"):
    key += "\n"
path = Path(os.environ["ASC_KEY_PATH"])
path.write_text(key)
path.chmod(0o600)
PY
  ok "App Store Connect API key installed for xcodebuild/altool"
}

inject_maps_key() {
  log "Inject iOS Google Maps key for $BUNDLE_ID"
  /usr/libexec/PlistBuddy -c "Set :GMSApiKey $IOS_MAPS_API_KEY" "$INFO_PLIST" 2>/dev/null ||
    /usr/libexec/PlistBuddy -c "Add :GMSApiKey string $IOS_MAPS_API_KEY" "$INFO_PLIST"
  export MAPS_API_KEY="$IOS_MAPS_API_KEY"
}

configure_flutter() {
  log "Configure Flutter production build"
  cd "$APP_DIR"
  flutter pub get
  flutter build ios --release --config-only \
    --target lib/main_prod.dart \
    --build-name "$VERSION_NAME_VALUE" \
    --build-number "$BUILD_NUMBER_VALUE" \
    --dart-define="APP_ENV=production" \
    --dart-define="RIDE360_RELEASE_BUILD=true" \
    --dart-define="DEV_BYPASS_ENABLED=false" \
    --dart-define="API_BASE_URL=$API_BASE_URL_VALUE" \
    --dart-define="WS_URL=wss://ride.365sakartvelo.com" \
    --dart-define="MAPS_API_KEY=$IOS_MAPS_API_KEY" \
    --dart-define="GOOGLE_MAPS_KEY=$IOS_MAPS_API_KEY" \
    --dart-define="HANGOVER_BUILD_APP_NAME=$DISPLAY_NAME" \
    --dart-define="HANGOVER_BUILD_VERSION_NAME=$VERSION_NAME_VALUE" \
    --dart-define="HANGOVER_BUILD_VERSION_CODE=$BUILD_NUMBER_VALUE" \
    --dart-define="HANGOVER_BUILD_TIMESTAMP=$BUILD_TIMESTAMP" \
    --dart-define="HANGOVER_BUILD_PACKAGE_ID=$BUNDLE_ID" \
    --dart-define="HANGOVER_BUILD_COMMIT=$GIT_COMMIT" \
    --dart-define="WS_KEY=${WS_KEY:-}" \
    --dart-define="SENTRY_DSN="
}

install_pods() {
  log "Install CocoaPods"
  cd "$APP_DIR"
  pod install --project-directory=ios
}

write_export_options() {
  log "Write ExportOptions.plist"
  mkdir -p "$ARTIFACT_DIR" "$EXPORT_PATH"
  cat > "$EXPORT_OPTIONS" <<EOF_PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>method</key>
  <string>app-store-connect</string>
  <key>teamID</key>
  <string>$APPLE_TEAM_ID</string>
  <key>signingStyle</key>
  <string>automatic</string>
  <key>stripSwiftSymbols</key>
  <true/>
</dict>
</plist>
EOF_PLIST
}

archive_and_export() {
  log "Archive $APP_NAME"
  rm -rf "$ARCHIVE_PATH" "$EXPORT_PATH"
  mkdir -p "$ARTIFACT_DIR" "$EXPORT_PATH"

  local auth_args=(
    -allowProvisioningUpdates
    -authenticationKeyPath "$ASC_KEY_PATH"
    -authenticationKeyID "$APP_STORE_CONNECT_KEY_ID"
    -authenticationKeyIssuerID "$APP_STORE_CONNECT_ISSUER_ID"
  )

  xcodebuild archive \
    -workspace "$IOS_DIR/Runner.xcworkspace" \
    -scheme Runner \
    -configuration Release \
    -destination "generic/platform=iOS" \
    -archivePath "$ARCHIVE_PATH" \
    "${auth_args[@]}" \
    DEVELOPMENT_TEAM="$APPLE_TEAM_ID" \
    PRODUCT_BUNDLE_IDENTIFIER="$BUNDLE_ID" \
    CODE_SIGN_STYLE=Automatic \
    CODE_SIGN_IDENTITY="Apple Distribution" \
    MAPS_API_KEY="$IOS_MAPS_API_KEY"

  log "Export signed IPA"
  xcodebuild -exportArchive \
    -archivePath "$ARCHIVE_PATH" \
    -exportPath "$EXPORT_PATH" \
    -exportOptionsPlist "$EXPORT_OPTIONS" \
    "${auth_args[@]}"

  IPA_SRC="$(find "$EXPORT_PATH" -name '*.ipa' -print | awk 'NR == 1 { print; exit }')"
  [[ -n "${IPA_SRC:-}" && -f "$IPA_SRC" ]] || fail "No IPA exported under $EXPORT_PATH"
  IPA_OUT="$ARTIFACT_DIR/${APP_NAME}-v${VERSION_NAME_VALUE}-${BUILD_NUMBER_VALUE}.ipa"
  cp "$IPA_SRC" "$IPA_OUT"
  shasum -a 256 "$IPA_OUT" > "$IPA_OUT.sha256"
  ok "IPA: $IPA_OUT"
}

verify_ipa_strings() {
  log "Verify production strings"
  TMP_VERIFY_DIR="$(mktemp -d "${TMPDIR:-/tmp}/ride360-gha-ipa.XXXXXX")"
  trap 'rm -rf "${TMP_VERIFY_DIR:-}"' EXIT
  unzip -q "$IPA_OUT" -d "$TMP_VERIFY_DIR"
  strings_file="$TMP_VERIFY_DIR/ipa-strings.txt"
  find "$TMP_VERIFY_DIR/Payload" -type f -print0 |
    xargs -0 strings 2>/dev/null > "$strings_file" || true

  forbidden_pattern='10\.0\.2\.2|127\.0\.0\.1:8000|localhost:8000|api\.hangover|api\.staging|https://ride\.365sakartvelo\.com/api/v1|http://ride\.365sakartvelo\.com'
  if grep -Eq "$forbidden_pattern" "$strings_file"; then
    grep -En "$forbidden_pattern" "$strings_file" >&2 || true
    fail "IPA contains a forbidden non-production string"
  fi
  ok "production IPA string scan passed"
}

upload_to_testflight() {
  log "Upload $APP_NAME to TestFlight"
  xcrun altool --upload-app \
    --type ios \
    --file "$IPA_OUT" \
    --apiKey "$APP_STORE_CONNECT_KEY_ID" \
    --apiIssuer "$APP_STORE_CONNECT_ISSUER_ID"
  ok "TestFlight upload submitted"
}

log "GitHub Actions iOS TestFlight build: $APP_NAME"
ok "bundle id: $BUNDLE_ID"
ok "version: $VERSION_NAME_VALUE ($BUILD_NUMBER_VALUE)"
ok "api: $API_BASE_URL_VALUE"

prepare_app_store_connect_key
inject_maps_key
configure_flutter
install_pods
write_export_options
archive_and_export
verify_ipa_strings
upload_to_testflight
