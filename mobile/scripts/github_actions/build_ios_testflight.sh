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

print_secret_presence() {
  local label="$1"
  local value="$2"
  if [[ -n "$value" ]]; then
    ok "$label: yes"
  else
    ok "$label: no"
  fi
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
CUSTOMER_APP_DIR="$REPO_ROOT/mobile/apps/customer_app"
DRIVER_APP_DIR="$REPO_ROOT/mobile/apps/driver_app"
ASC_PRIVATE_KEYS_DIR="$HOME/.appstoreconnect/private_keys"
MANUAL_PROFILE_DIR="$HOME/Library/MobileDevice/Provisioning Profiles"
MANUAL_SIGNING_PROFILE_SECRET=""
MANUAL_SIGNING_CERT_SECRET="${IOS_DISTRIBUTION_CERTIFICATE_BASE64:-${IOS_DISTRIBUTION_CERTIFICATE_P12_BASE64:-}}"
MANUAL_SIGNING_PROFILE_UUID=""
MANUAL_SIGNING_PROFILE_NAME=""
MANUAL_SIGNING_PROFILE_BUNDLE_ID=""
MANUAL_SIGNING_PROFILE_TEAM_ID=""
MANUAL_SIGNING_KEYCHAIN_PATH=""

case "$ROLE" in
  customer)
    MANUAL_SIGNING_PROFILE_SECRET="${IOS_CUSTOMER_PROVISIONING_PROFILE_BASE64:-${IOS_CUSTOMER_APP_STORE_PROFILE_BASE64:-${IOS_APP_STORE_PROFILE_BASE64:-}}}"
    ;;
  driver)
    MANUAL_SIGNING_PROFILE_SECRET="${IOS_DRIVER_PROVISIONING_PROFILE_BASE64:-${IOS_DRIVER_APP_STORE_PROFILE_BASE64:-${IOS_APP_STORE_PROFILE_BASE64:-}}}"
    ;;
esac

SIGNING_STYLE_VALUE="manual"

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
case "$ROLE" in
  customer) role_profile_secret_name="IOS_CUSTOMER_PROVISIONING_PROFILE_BASE64" ;;
  driver) role_profile_secret_name="IOS_DRIVER_PROVISIONING_PROFILE_BASE64" ;;
esac

log "Manual signing secret diagnostics"
print_secret_presence "manual certificate secret present" "$MANUAL_SIGNING_CERT_SECRET"
print_secret_presence "$ROLE provisioning profile secret present" "$MANUAL_SIGNING_PROFILE_SECRET"
print_secret_presence "keychain password secret present" "${IOS_KEYCHAIN_PASSWORD:-}"

[[ -n "$MANUAL_SIGNING_PROFILE_SECRET" ]] ||
  fail "Manual iOS signing requires $role_profile_secret_name"
[[ -n "$MANUAL_SIGNING_CERT_SECRET" ]] ||
  fail "Manual iOS signing requires IOS_DISTRIBUTION_CERTIFICATE_BASE64"
[[ -n "${IOS_DISTRIBUTION_CERTIFICATE_PASSWORD:-}" ]] ||
  fail "Manual iOS signing requires IOS_DISTRIBUTION_CERTIFICATE_PASSWORD"
[[ -n "${IOS_KEYCHAIN_PASSWORD:-}" ]] ||
  fail "Manual iOS signing requires IOS_KEYCHAIN_PASSWORD"

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
FIREBASE_IOS_CI_DISABLED="true"
FIREBASE_SCAN_PATTERN='firebase_core|firebase_messaging|FirebaseMessaging|FirebaseCore|GoogleDataTransport'

print_xcode_debug() {
  log "Select Xcode for Flutter iOS build"
  ok "using the default Xcode selected by the GitHub macOS runner"

  log "xcode-select -p"
  xcode-select -p || true

  log "Installed Xcode apps"
  ls /Applications | grep Xcode || true

  log "xcodebuild -version"
  xcodebuild -version || true
}

remove_pubspec_dependency() {
  local pubspec_path="$1"
  local package_name="$2"

  [[ -f "$pubspec_path" ]] || return

  local tmp_path="$pubspec_path.tmp"
  awk -v package_name="$package_name" '
    $0 ~ "^[[:space:]]*" package_name ":" { next }
    { print }
  ' "$pubspec_path" > "$tmp_path"
  mv "$tmp_path" "$pubspec_path"
}

clean_flutter_generated_dependency_state() {
  local app_path=""
  for app_path in "$CUSTOMER_APP_DIR" "$DRIVER_APP_DIR"; do
    rm -rf \
      "$app_path/.dart_tool" \
      "$app_path/.flutter-plugins" \
      "$app_path/.flutter-plugins-dependencies" \
      "$app_path/pubspec.lock" \
      "$app_path/ios/.symlinks" \
      "$app_path/ios/Pods" \
      "$app_path/ios/Podfile.lock" \
      "$app_path/ios/Runner/GeneratedPluginRegistrant.m" \
      "$app_path/ios/Runner.xcworkspace/xcshareddata/swiftpm/Package.resolved" \
      "$app_path/ios/Runner.xcodeproj/project.xcworkspace/xcshareddata/swiftpm/Package.resolved"
  done

  find "$REPO_ROOT/mobile/packages" -mindepth 2 -maxdepth 2 -name ".dart_tool" -type d -prune -exec rm -rf {} + 2>/dev/null || true
  find "$REPO_ROOT/mobile/packages" -mindepth 2 -maxdepth 2 -name "pubspec.lock" -type f -delete 2>/dev/null || true
}

disable_firebase_for_ios_ci() {
  log "Disable Firebase for this iOS CI checkout"
  ok "reason: GitHub Actions iOS lane builds without Firebase until Firebase Swift/Xcode compatibility is resolved"

  remove_pubspec_dependency "$REPO_ROOT/mobile/packages/core/pubspec.yaml" "firebase_core"
  remove_pubspec_dependency "$REPO_ROOT/mobile/packages/core/pubspec.yaml" "firebase_messaging"
  remove_pubspec_dependency "$CUSTOMER_APP_DIR/pubspec.yaml" "firebase_core"
  remove_pubspec_dependency "$CUSTOMER_APP_DIR/pubspec.yaml" "firebase_messaging"
  remove_pubspec_dependency "$DRIVER_APP_DIR/pubspec.yaml" "firebase_core"
  remove_pubspec_dependency "$DRIVER_APP_DIR/pubspec.yaml" "firebase_messaging"
  clean_flutter_generated_dependency_state

  cat > "$REPO_ROOT/mobile/packages/core/lib/src/push/firebase_push_service.dart" <<'EOF_DART'
import '../logging/app_logger.dart';
import 'push_service.dart';

/// No-op Firebase push adapter used only by iOS cloud release builds until the
/// Firebase Swift/Xcode compatibility issue is resolved.
class FirebasePushService extends NullPushService {
  FirebasePushService({required AppLogger logger}) {
    logger.i('Firebase push disabled for this iOS CI build');
  }
}
EOF_DART

  ok "firebase_core/firebase_messaging removed and stale iOS generated state cleared before flutter pub get; Maps remains enabled"
}

verify_no_firebase_for_ios_ci() {
  local stage="$1"
  local scan_output="$ARTIFACT_DIR/firebase-scan.txt"

  log "Verify Firebase is absent after $stage"
  mkdir -p "$ARTIFACT_DIR"
  printf 'grep -R -E "%s" mobile/apps/customer_app mobile/apps/driver_app mobile/packages mobile/apps/customer_app/ios mobile/apps/driver_app/ios\n' "$FIREBASE_SCAN_PATTERN"

  (
    cd "$REPO_ROOT"
    find \
      mobile/apps/customer_app \
      mobile/apps/driver_app \
      mobile/packages \
      mobile/apps/customer_app/ios \
      mobile/apps/driver_app/ios \
      \( -path "*/android/*" -o -path "*/build/*" -o -path "*/.git/*" \) -prune -o \
      -type f \
      ! -name "*.md" \
      ! -name "*.markdown" \
      -print0 |
      xargs -0 grep -n -E "$FIREBASE_SCAN_PATTERN" 2>/dev/null || true
  ) > "$scan_output"

  if [[ -s "$scan_output" ]]; then
    cat "$scan_output" >&2
    fail "Firebase dependency still appears in the iOS CI build tree after $stage"
  fi

  ok "no active Firebase dependency found after $stage"
}

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

decode_base64_secret() {
  local secret_value="$1"
  local output_path="$2"
  SECRET_VALUE="$secret_value" OUTPUT_PATH="$output_path" python3 - <<'PY'
import base64
import os
from pathlib import Path

raw = os.environ["SECRET_VALUE"].strip()
normalized = "".join(raw.split())
Path(os.environ["OUTPUT_PATH"]).write_bytes(base64.b64decode(normalized))
PY
}

prepare_manual_signing_assets() {
  log "Prepare manual App Store signing assets"
  mkdir -p "$MANUAL_PROFILE_DIR"

  local cert_path="$ARTIFACT_DIR/ios_distribution.p12"
  local profile_path="$ARTIFACT_DIR/app_store_profile.mobileprovision"
  local profile_plist="$ARTIFACT_DIR/app_store_profile.plist"
  local keychain_password="$IOS_KEYCHAIN_PASSWORD"

  mkdir -p "$ARTIFACT_DIR"
  decode_base64_secret "$MANUAL_SIGNING_CERT_SECRET" "$cert_path"
  decode_base64_secret "$MANUAL_SIGNING_PROFILE_SECRET" "$profile_path"

  MANUAL_SIGNING_KEYCHAIN_PATH="$ARTIFACT_DIR/ride360-signing.keychain-db"
  rm -f "$MANUAL_SIGNING_KEYCHAIN_PATH"
  security create-keychain -p "$keychain_password" "$MANUAL_SIGNING_KEYCHAIN_PATH"
  security set-keychain-settings -lut 21600 "$MANUAL_SIGNING_KEYCHAIN_PATH"
  security unlock-keychain -p "$keychain_password" "$MANUAL_SIGNING_KEYCHAIN_PATH"
  security import "$cert_path" \
    -k "$MANUAL_SIGNING_KEYCHAIN_PATH" \
    -P "$IOS_DISTRIBUTION_CERTIFICATE_PASSWORD" \
    -T /usr/bin/codesign \
    -T /usr/bin/security
  security default-keychain -s "$MANUAL_SIGNING_KEYCHAIN_PATH"
  security list-keychains -d user -s "$MANUAL_SIGNING_KEYCHAIN_PATH" $(security list-keychains -d user | tr -d '"')
  security set-key-partition-list \
    -S apple-tool:,apple:,codesign: \
    -s \
    -k "$keychain_password" \
    "$MANUAL_SIGNING_KEYCHAIN_PATH"

  log "Imported certificate identities"
  security find-identity -v -p codesigning || true
  if ! security find-identity -v -p codesigning "$MANUAL_SIGNING_KEYCHAIN_PATH" | grep -Eq 'Apple Distribution|iOS Distribution'; then
    fail "Imported certificate is not an Apple/iOS Distribution signing identity"
  fi

  security cms -D -i "$profile_path" > "$profile_plist"
  MANUAL_SIGNING_PROFILE_NAME="$(/usr/libexec/PlistBuddy -c 'Print :Name' "$profile_plist")"
  local profile_app_id
  local profile_dest
  MANUAL_SIGNING_PROFILE_UUID="$(/usr/libexec/PlistBuddy -c 'Print :UUID' "$profile_plist")"
  profile_app_id="$(/usr/libexec/PlistBuddy -c 'Print :Entitlements:application-identifier' "$profile_plist" 2>/dev/null || true)"
  MANUAL_SIGNING_PROFILE_TEAM_ID="$(/usr/libexec/PlistBuddy -c 'Print :TeamIdentifier:0' "$profile_plist" 2>/dev/null || true)"
  MANUAL_SIGNING_PROFILE_BUNDLE_ID="${profile_app_id#*.}"

  [[ -n "$MANUAL_SIGNING_PROFILE_NAME" ]] || fail "Could not read provisioning profile name"
  [[ -n "$MANUAL_SIGNING_PROFILE_UUID" ]] || fail "Could not read provisioning profile UUID"
  [[ "$MANUAL_SIGNING_PROFILE_TEAM_ID" == "$APPLE_TEAM_ID" ]] ||
    fail "Provisioning profile team '$MANUAL_SIGNING_PROFILE_TEAM_ID' does not match $APPLE_TEAM_ID"
  case "$profile_app_id" in
    "$APPLE_TEAM_ID.$BUNDLE_ID"|*".$BUNDLE_ID") ;;
    *) fail "Provisioning profile '$MANUAL_SIGNING_PROFILE_NAME' does not match $BUNDLE_ID" ;;
  esac

  profile_dest="$MANUAL_PROFILE_DIR/$MANUAL_SIGNING_PROFILE_UUID.mobileprovision"
  cp "$profile_path" "$profile_dest"

  log "Installed provisioning profile"
  ok "UUID: $MANUAL_SIGNING_PROFILE_UUID"
  ok "Name: $MANUAL_SIGNING_PROFILE_NAME"
  ok "application-identifier: $profile_app_id"
  ok "bundle id: $MANUAL_SIGNING_PROFILE_BUNDLE_ID"
  ok "TeamIdentifier: $MANUAL_SIGNING_PROFILE_TEAM_ID"
  ok "path: $profile_dest"
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
  verify_no_firebase_for_ios_ci "flutter pub get"
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
  verify_no_firebase_for_ios_ci "Flutter iOS config generation"
}

install_pods() {
  log "Install CocoaPods"
  cd "$APP_DIR"
  pod install --project-directory=ios
  verify_no_firebase_for_ios_ci "pod install"
}

configure_xcode_signing() {
  log "Configure Runner signing and disable dependency signing"
  ruby - "$IOS_DIR/Runner.xcodeproj" \
    "$IOS_DIR/Pods/Pods.xcodeproj" \
    "$BUNDLE_ID" \
    "$APPLE_TEAM_ID" \
    "$MANUAL_SIGNING_PROFILE_NAME" \
    "$MANUAL_SIGNING_PROFILE_UUID" <<'RUBY'
require 'xcodeproj'

runner_project_path, pods_project_path, bundle_id, team_id, profile_name, profile_uuid = ARGV

def runner_release_config?(config)
  %w[Release Profile].include?(config.name)
end

def clear_signing_identity(settings)
  settings.delete('CODE_SIGN_IDENTITY')
  settings.delete('CODE_SIGN_IDENTITY[sdk=appletvos*]')
  settings.delete('CODE_SIGN_IDENTITY[sdk=iphoneos*]')
  settings.delete('CODE_SIGN_IDENTITY[sdk=watchos*]')
  settings.delete('EXPANDED_CODE_SIGN_IDENTITY')
end

def disable_target_signing(target)
  target.build_configurations.each do |config|
    settings = config.build_settings
    settings['CODE_SIGNING_ALLOWED'] = 'NO'
    settings['CODE_SIGNING_REQUIRED'] = 'NO'
    settings['CODE_SIGN_IDENTITY'] = ''
    settings['CODE_SIGN_IDENTITY[sdk=iphoneos*]'] = ''
    settings['EXPANDED_CODE_SIGN_IDENTITY'] = ''
    settings.delete('DEVELOPMENT_TEAM')
    settings.delete('PROVISIONING_PROFILE')
    settings.delete('PROVISIONING_PROFILE_SPECIFIER')
  end
end

def configure_runner_target(target, bundle_id, team_id, profile_name, profile_uuid)
  target.build_configurations.each do |config|
    settings = config.build_settings
    settings['DEVELOPMENT_TEAM'] = team_id
    settings['PRODUCT_BUNDLE_IDENTIFIER'] = bundle_id
    settings['CODE_SIGN_STYLE'] = 'Manual'
    settings['PROVISIONING_PROFILE_SPECIFIER'] = profile_name
    settings['PROVISIONING_PROFILE'] = profile_uuid
    settings['CODE_SIGN_IDENTITY'] = 'Apple Distribution'
    settings['CODE_SIGN_IDENTITY[sdk=iphoneos*]'] = 'Apple Distribution'
    settings.delete('EXPANDED_CODE_SIGN_IDENTITY')

    next unless runner_release_config?(config)

    settings.delete('CODE_SIGNING_ALLOWED')
    settings.delete('CODE_SIGNING_REQUIRED')
  end
end

runner_project = Xcodeproj::Project.open(runner_project_path)
runner_target = runner_project.targets.find { |target| target.name == 'Runner' }
abort("Runner target not found in #{runner_project_path}") unless runner_target

runner_project.targets.each do |target|
  if target.name == 'Runner'
    configure_runner_target(target, bundle_id, team_id, profile_name, profile_uuid)
  else
    disable_target_signing(target)
  end
end

runner_project.save

if File.directory?(pods_project_path)
  pods_project = Xcodeproj::Project.open(pods_project_path)
  pods_project.targets.each do |target|
    disable_target_signing(target)
  end
  pods_project.save
end
RUBY
  ok "Runner uses manual App Store signing; dependency target signing is disabled"
}

print_runner_project_signing_debug() {
  log "Runner project signing settings"
  grep -n "CODE_SIGN_STYLE\\|PROVISIONING_PROFILE_SPECIFIER\\|PROVISIONING_PROFILE\\|CODE_SIGN_IDENTITY" \
    "$IOS_DIR/Runner.xcodeproj/project.pbxproj" || true
}

print_archive_debug() {
  log "Archive command"
  printf ' '
  printf '%q ' "$@"
  printf '\n'

  log "Signing identity scan"
  (
    cd "$REPO_ROOT"
    apple_distribution_pattern="Apple Distrib""ution"
    grep -R "CODE_SIGN_IDENTITY\\|$apple_distribution_pattern" \
      .github \
      mobile/scripts \
      mobile/apps/customer_app/ios \
      mobile/apps/driver_app/ios || true
  )
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
  <string>manual</string>
  <key>provisioningProfiles</key>
  <dict>
    <key>$BUNDLE_ID</key>
    <string>$MANUAL_SIGNING_PROFILE_NAME</string>
  </dict>
  <key>stripSwiftSymbols</key>
  <true/>
</dict>
</plist>
EOF_PLIST
}

print_signing_debug() {
  log "Signing selection"
  ok "bundle id: $BUNDLE_ID"
  ok "team id: $APPLE_TEAM_ID"
  ok "selected signing style: $SIGNING_STYLE_VALUE"
  ok "manual App Store profile: $MANUAL_SIGNING_PROFILE_NAME"
  ok "manual App Store profile UUID: $MANUAL_SIGNING_PROFILE_UUID"
  ok "manual signing keychain: $MANUAL_SIGNING_KEYCHAIN_PATH"

  log "ExportOptions.plist"
  sed -n '1,220p' "$EXPORT_OPTIONS"
}

archive_and_export() {
  log "Archive $APP_NAME"
  rm -rf "$ARCHIVE_PATH" "$EXPORT_PATH"
  mkdir -p "$ARTIFACT_DIR" "$EXPORT_PATH"

  local -a archive_cmd
  archive_cmd=(
    xcodebuild archive
    -workspace "$IOS_DIR/Runner.xcworkspace"
    -scheme Runner
    -configuration Release
    -destination "generic/platform=iOS"
    -archivePath "$ARCHIVE_PATH"
    -allowProvisioningUpdates
    DEVELOPMENT_TEAM="$APPLE_TEAM_ID"
    CODE_SIGN_STYLE=Manual
    PROVISIONING_PROFILE_SPECIFIER="$MANUAL_SIGNING_PROFILE_NAME"
    PROVISIONING_PROFILE="$MANUAL_SIGNING_PROFILE_UUID"
    OTHER_CODE_SIGN_FLAGS="--keychain $MANUAL_SIGNING_KEYCHAIN_PATH"
  )

  print_signing_debug
  print_archive_debug "${archive_cmd[@]}"
  "${archive_cmd[@]}"

  log "Export signed IPA"
  xcodebuild -exportArchive \
    -archivePath "$ARCHIVE_PATH" \
    -exportPath "$EXPORT_PATH" \
    -exportOptionsPlist "$EXPORT_OPTIONS"

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

print_xcode_debug
prepare_app_store_connect_key
prepare_manual_signing_assets
inject_maps_key
disable_firebase_for_ios_ci
configure_flutter
install_pods
configure_xcode_signing
print_runner_project_signing_debug
write_export_options
archive_and_export
verify_ipa_strings
upload_to_testflight
