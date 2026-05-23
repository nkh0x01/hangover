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
MANUAL_PROFILE_DIR="$HOME/Library/MobileDevice/Provisioning Profiles"
MANUAL_SIGNING_PROFILE_SECRET="${IOS_APP_STORE_PROFILE_BASE64:-}"
MANUAL_SIGNING_CERT_SECRET="${IOS_DISTRIBUTION_CERTIFICATE_BASE64:-${IOS_DISTRIBUTION_CERTIFICATE_P12_BASE64:-}}"
MANUAL_SIGNING_PROFILE_NAME=""
MANUAL_SIGNING_KEYCHAIN_PATH=""

case "$ROLE" in
  customer)
    MANUAL_SIGNING_PROFILE_SECRET="${IOS_CUSTOMER_APP_STORE_PROFILE_BASE64:-$MANUAL_SIGNING_PROFILE_SECRET}"
    ;;
  driver)
    MANUAL_SIGNING_PROFILE_SECRET="${IOS_DRIVER_APP_STORE_PROFILE_BASE64:-$MANUAL_SIGNING_PROFILE_SECRET}"
    ;;
esac

if [[ -n "$MANUAL_SIGNING_PROFILE_SECRET" ]]; then
  SIGNING_STYLE_VALUE="${IOS_SIGNING_STYLE:-manual}"
else
  SIGNING_STYLE_VALUE="${IOS_SIGNING_STYLE:-automatic}"
fi

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
case "$SIGNING_STYLE_VALUE" in
  automatic|manual) ;;
  *) fail "IOS_SIGNING_STYLE must be automatic or manual, got '$SIGNING_STYLE_VALUE'" ;;
esac
if [[ "$SIGNING_STYLE_VALUE" == "manual" ]]; then
  case "$ROLE" in
    customer) role_profile_secret_name="IOS_CUSTOMER_APP_STORE_PROFILE_BASE64" ;;
    driver) role_profile_secret_name="IOS_DRIVER_APP_STORE_PROFILE_BASE64" ;;
  esac
  [[ -n "$MANUAL_SIGNING_PROFILE_SECRET" ]] ||
    fail "Manual iOS signing requires $role_profile_secret_name or IOS_APP_STORE_PROFILE_BASE64"
  [[ -n "$MANUAL_SIGNING_CERT_SECRET" ]] ||
    fail "Manual iOS signing requires IOS_DISTRIBUTION_CERTIFICATE_BASE64"
  [[ -n "${IOS_DISTRIBUTION_CERTIFICATE_PASSWORD:-}" ]] ||
    fail "Manual iOS signing requires IOS_DISTRIBUTION_CERTIFICATE_PASSWORD"
fi

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
  if [[ "$SIGNING_STYLE_VALUE" != "manual" ]]; then
    return
  fi

  log "Prepare manual App Store signing assets"
  mkdir -p "$MANUAL_PROFILE_DIR"

  local cert_path="$ARTIFACT_DIR/ios_distribution.p12"
  local profile_path="$ARTIFACT_DIR/app_store_profile.mobileprovision"
  local profile_plist="$ARTIFACT_DIR/app_store_profile.plist"
  local keychain_password

  mkdir -p "$ARTIFACT_DIR"
  decode_base64_secret "$MANUAL_SIGNING_CERT_SECRET" "$cert_path"
  decode_base64_secret "$MANUAL_SIGNING_PROFILE_SECRET" "$profile_path"

  keychain_password="$(uuidgen)"
  MANUAL_SIGNING_KEYCHAIN_PATH="$ARTIFACT_DIR/ride360-signing.keychain-db"
  security create-keychain -p "$keychain_password" "$MANUAL_SIGNING_KEYCHAIN_PATH"
  security set-keychain-settings -lut 21600 "$MANUAL_SIGNING_KEYCHAIN_PATH"
  security unlock-keychain -p "$keychain_password" "$MANUAL_SIGNING_KEYCHAIN_PATH"
  security import "$cert_path" \
    -k "$MANUAL_SIGNING_KEYCHAIN_PATH" \
    -P "$IOS_DISTRIBUTION_CERTIFICATE_PASSWORD" \
    -T /usr/bin/codesign \
    -T /usr/bin/security
  security list-keychains -d user -s "$MANUAL_SIGNING_KEYCHAIN_PATH" $(security list-keychains -d user | tr -d '"')
  security set-key-partition-list \
    -S apple-tool:,apple:,codesign: \
    -s \
    -k "$keychain_password" \
    "$MANUAL_SIGNING_KEYCHAIN_PATH"

  security cms -D -i "$profile_path" > "$profile_plist"
  MANUAL_SIGNING_PROFILE_NAME="$(/usr/libexec/PlistBuddy -c 'Print :Name' "$profile_plist")"
  local profile_uuid
  local profile_app_id
  profile_uuid="$(/usr/libexec/PlistBuddy -c 'Print :UUID' "$profile_plist")"
  profile_app_id="$(/usr/libexec/PlistBuddy -c 'Print :Entitlements:application-identifier' "$profile_plist" 2>/dev/null || true)"

  [[ -n "$MANUAL_SIGNING_PROFILE_NAME" ]] || fail "Could not read provisioning profile name"
  [[ -n "$profile_uuid" ]] || fail "Could not read provisioning profile UUID"
  case "$profile_app_id" in
    "$APPLE_TEAM_ID.$BUNDLE_ID"|*".$BUNDLE_ID") ;;
    *) fail "Provisioning profile '$MANUAL_SIGNING_PROFILE_NAME' does not match $BUNDLE_ID" ;;
  esac

  cp "$profile_path" "$MANUAL_PROFILE_DIR/$profile_uuid.mobileprovision"
  ok "manual App Store profile: $MANUAL_SIGNING_PROFILE_NAME"
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

configure_xcode_signing() {
  log "Configure Runner signing and disable dependency signing"
  ruby - "$IOS_DIR/Runner.xcodeproj" \
    "$IOS_DIR/Pods/Pods.xcodeproj" \
    "$BUNDLE_ID" \
    "$APPLE_TEAM_ID" \
    "$SIGNING_STYLE_VALUE" \
    "$MANUAL_SIGNING_PROFILE_NAME" <<'RUBY'
require 'xcodeproj'

runner_project_path, pods_project_path, bundle_id, team_id, signing_style, profile_name = ARGV

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

def configure_runner_target(target, bundle_id, team_id, signing_style, profile_name)
  target.build_configurations.each do |config|
    settings = config.build_settings
    settings['DEVELOPMENT_TEAM'] = team_id
    settings['PRODUCT_BUNDLE_IDENTIFIER'] = bundle_id
    clear_signing_identity(settings)

    if signing_style == 'manual'
      settings['CODE_SIGN_STYLE'] = 'Manual'
      settings['PROVISIONING_PROFILE_SPECIFIER'] = profile_name
      settings['PROVISIONING_PROFILE'] = ''
    else
      settings['CODE_SIGN_STYLE'] = 'Automatic'
      settings.delete('PROVISIONING_PROFILE')
      settings.delete('PROVISIONING_PROFILE_SPECIFIER')
    end

    next unless runner_release_config?(config)

    settings.delete('CODE_SIGNING_ALLOWED')
    settings.delete('CODE_SIGNING_REQUIRED')
  end
end

runner_project = Xcodeproj::Project.open(runner_project_path)
runner_target = runner_project.targets.find { |target| target.name == 'Runner' }
abort("Runner target not found in #{runner_project_path}") unless runner_target

runner_project.build_configurations.each do |config|
  clear_signing_identity(config.build_settings)
end

runner_project.targets.each do |target|
  if target.name == 'Runner'
    configure_runner_target(target, bundle_id, team_id, signing_style, profile_name)
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
  ok "Runner uses automatic signing; dependency target signing is disabled"
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
  if [[ "$SIGNING_STYLE_VALUE" == "manual" ]]; then
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
  else
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
  fi
}

print_signing_debug() {
  log "Signing selection"
  ok "bundle id: $BUNDLE_ID"
  ok "team id: $APPLE_TEAM_ID"
  ok "selected signing style: $SIGNING_STYLE_VALUE"
  if [[ "$SIGNING_STYLE_VALUE" == "automatic" ]]; then
    ok "archive signing: disabled; exportArchive performs automatic App Store signing"
  else
    ok "manual App Store profile: $MANUAL_SIGNING_PROFILE_NAME"
  fi

  log "ExportOptions.plist"
  sed -n '1,220p' "$EXPORT_OPTIONS"
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

  local -a archive_cmd
  if [[ "$SIGNING_STYLE_VALUE" == "manual" ]]; then
    archive_cmd=(
      xcodebuild archive
      -workspace "$IOS_DIR/Runner.xcworkspace"
      -scheme Runner
      -configuration Release
      -destination "generic/platform=iOS"
      -archivePath "$ARCHIVE_PATH"
      "${auth_args[@]}"
      DEVELOPMENT_TEAM="$APPLE_TEAM_ID"
    )
  else
    archive_cmd=(
      xcodebuild archive
      -workspace "$IOS_DIR/Runner.xcworkspace"
      -scheme Runner
      -configuration Release
      -destination "generic/platform=iOS"
      -archivePath "$ARCHIVE_PATH"
      "${auth_args[@]}"
      DEVELOPMENT_TEAM="$APPLE_TEAM_ID"
      CODE_SIGN_STYLE=Automatic
      CODE_SIGNING_ALLOWED=NO
    )
  fi

  print_signing_debug
  print_archive_debug "${archive_cmd[@]}"
  "${archive_cmd[@]}"

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
prepare_manual_signing_assets
inject_maps_key
configure_flutter
install_pods
configure_xcode_signing
write_export_options
archive_and_export
verify_ipa_strings
upload_to_testflight
