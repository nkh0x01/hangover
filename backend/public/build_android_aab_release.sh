#!/usr/bin/env bash
# Common production Android App Bundle builder for Ride 360.
#
# Entry points:
#   build_customer_aab_release.command
#   build_driver_aab_release.command
#
# Secrets are kept outside the repo under ~/.ride360/android by default.

set -euo pipefail

if [[ -t 1 ]]; then
  G=$'\e[1;32m'; R=$'\e[1;31m'; Y=$'\e[1;33m'; B=$'\e[1;36m'; D=$'\e[2m'; X=$'\e[0m'
else
  G='' R='' Y='' B='' D='' X=''
fi

step() { echo; echo "${B}━━━━ $1 ━━━━${X}"; }
ok() { echo "${G}✔${X} $1"; }
note() { echo "${D}    $1${X}"; }
warn() { echo "${Y}!${X} $1"; }
fail() { echo; echo "${R}✖ $1${X}" >&2; exit 1; }

ROLE="${1:-}"
case "$ROLE" in
  customer)
    APP="customer_app"
    LABEL="Customer"
    APP_NAME="Ride 360"
    PACKAGE_ID="app.ride360.customer"
    OUTPUT_PREFIX="Ride360-Customer"
    DOWNLOAD_PREFIX="ride360-customer"
    KEY_ALIAS="ride360_customer_upload"
    KEYSTORE_BASENAME="ride360-customer-upload"
    FLUTTER_FLAVOR_ARGS=()
    EXPECTED_AAB_REL="build/app/outputs/bundle/release/app-release.aab"
    ;;
  driver)
    APP="driver_app"
    LABEL="Driver"
    APP_NAME="Ride 360 Driver"
    PACKAGE_ID="app.ride360.driver"
    OUTPUT_PREFIX="Ride360-Driver"
    DOWNLOAD_PREFIX="ride360-driver"
    KEY_ALIAS="ride360_driver_upload"
    KEYSTORE_BASENAME="ride360-driver-upload"
    FLUTTER_FLAVOR_ARGS=(--flavor prod)
    EXPECTED_AAB_REL="build/app/outputs/bundle/prodRelease/app-prod-release.aab"
    ;;
  *)
    fail "Usage: $0 customer|driver"
    ;;
esac

ORIGINAL_REPO="${RIDE360_REPO:-$HOME/Desktop/hangover}"
BUILD_REPO="${RIDE360_BUILD_REPO:-$HOME/Desktop/hangover_build_${ROLE}_release_aab}"
ORIGINAL_APP_DIR="$ORIGINAL_REPO/mobile/apps/$APP"
BUILD_APP_DIR="$BUILD_REPO/mobile/apps/$APP"
PUBLIC_DIR="$ORIGINAL_REPO/backend/public"
DOWNLOADS_DIR="$PUBLIC_DIR/downloads"
SECURE_DIR="${RIDE360_ANDROID_SIGNING_DIR:-$HOME/.ride360/android}"
KEYSTORE_FILE="$SECURE_DIR/${KEYSTORE_BASENAME}.jks"
KEY_PROPERTIES_FILE="$SECURE_DIR/${KEYSTORE_BASENAME}.properties"

API_BASE_URL_VALUE="${API_BASE_URL:-https://ride.365sakartvelo.com}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/api/v1}"
API_BASE_URL_VALUE="${API_BASE_URL_VALUE%/}"
if [[ "$API_BASE_URL_VALUE" != "https://ride.365sakartvelo.com" ]]; then
  fail "Refusing release build with API_BASE_URL='$API_BASE_URL_VALUE'. Production must use https://ride.365sakartvelo.com"
fi

[[ -d "$ORIGINAL_APP_DIR" ]] || fail "App directory not found: $ORIGINAL_APP_DIR"
command -v flutter >/dev/null 2>&1 || fail "flutter not found on PATH"
command -v rsync >/dev/null 2>&1 || fail "rsync not found on PATH"
command -v keytool >/dev/null 2>&1 || fail "keytool not found on PATH"
command -v jarsigner >/dev/null 2>&1 || fail "jarsigner not found on PATH"
command -v openssl >/dev/null 2>&1 || fail "openssl not found on PATH"
command -v rg >/dev/null 2>&1 || fail "rg not found on PATH"
command -v unzip >/dev/null 2>&1 || fail "unzip not found on PATH"
command -v strings >/dev/null 2>&1 || fail "strings not found on PATH"

if [[ -z "${ANDROID_HOME:-}" && -d "$HOME/Library/Android/sdk" ]]; then
  export ANDROID_HOME="$HOME/Library/Android/sdk"
  export PATH="$PATH:$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin"
fi

version_line="$(awk '$1 == "version:" { print $2; exit }' "$ORIGINAL_APP_DIR/pubspec.yaml")"
default_version_name="${version_line%%+*}"
if [[ "$version_line" == *"+"* ]]; then
  default_version_code="${version_line##*+}"
else
  default_version_code="1"
fi

VERSION_NAME_VALUE="${VERSION_NAME:-$default_version_name}"
VERSION_CODE_VALUE="${VERSION_CODE:-$default_version_code}"
if [[ "$ROLE" == "customer" ]]; then
  VERSION_NAME_VALUE="${CUSTOMER_VERSION_NAME:-$VERSION_NAME_VALUE}"
  VERSION_CODE_VALUE="${CUSTOMER_VERSION_CODE:-$VERSION_CODE_VALUE}"
else
  VERSION_NAME_VALUE="${DRIVER_VERSION_NAME:-$VERSION_NAME_VALUE}"
  VERSION_CODE_VALUE="${DRIVER_VERSION_CODE:-$VERSION_CODE_VALUE}"
fi

[[ "$VERSION_NAME_VALUE" =~ ^[0-9]+(\.[0-9A-Za-z_-]+)*$ ]] ||
  fail "Invalid VERSION_NAME '$VERSION_NAME_VALUE'"
[[ "$VERSION_CODE_VALUE" =~ ^[1-9][0-9]*$ ]] ||
  fail "Invalid VERSION_CODE '$VERSION_CODE_VALUE' (must be a positive integer)"

OUTPUT_VERSION_LABEL="${OUTPUT_VERSION_LABEL:-${VERSION_NAME_VALUE}+${VERSION_CODE_VALUE}}"
DESKTOP_AAB="$HOME/Desktop/${OUTPUT_PREFIX}-v${OUTPUT_VERSION_LABEL}.aab"
DOWNLOAD_AAB="$DOWNLOADS_DIR/${DOWNLOAD_PREFIX}-v${OUTPUT_VERSION_LABEL}.aab"

random_secret() {
  openssl rand -base64 36 | tr -d '\n' | tr '/+' '_-' | cut -c 1-32
}

ensure_signing() {
  step "Signing key"
  umask 077
  mkdir -p "$SECURE_DIR"
  chmod 700 "$SECURE_DIR" 2>/dev/null || true

  if [[ ! -f "$KEY_PROPERTIES_FILE" ]]; then
    if [[ -f "$KEYSTORE_FILE" ]]; then
      fail "Keystore exists but properties file is missing: $KEY_PROPERTIES_FILE"
    fi

    local store_pass key_pass
    store_pass="$(random_secret)"
    key_pass="$store_pass"

    keytool -genkeypair -v \
      -keystore "$KEYSTORE_FILE" \
      -storetype PKCS12 \
      -storepass "$store_pass" \
      -keypass "$key_pass" \
      -keyalg RSA \
      -keysize 2048 \
      -validity 10000 \
      -alias "$KEY_ALIAS" \
      -dname "CN=${APP_NAME} Upload, OU=Mobile, O=Ride 360, L=Tbilisi, ST=Tbilisi, C=GE" >/dev/null

    cat > "$KEY_PROPERTIES_FILE" <<EOF_PROPS
storeFile=$KEYSTORE_FILE
storePassword=$store_pass
keyAlias=$KEY_ALIAS
keyPassword=$key_pass
EOF_PROPS
    chmod 600 "$KEY_PROPERTIES_FILE" "$KEYSTORE_FILE" 2>/dev/null || true
    ok "generated upload keystore: $KEYSTORE_FILE"
    warn "Back up $KEYSTORE_FILE and $KEY_PROPERTIES_FILE. Losing this upload key can block future Play updates."
  else
    ok "using existing upload keystore properties: $KEY_PROPERTIES_FILE"
  fi

  # shellcheck disable=SC1090
  . "$KEY_PROPERTIES_FILE"

  [[ -f "${storeFile:-}" ]] || fail "storeFile does not exist: ${storeFile:-missing}"
  [[ -n "${storePassword:-}" ]] || fail "storePassword missing in $KEY_PROPERTIES_FILE"
  [[ -n "${keyAlias:-}" ]] || fail "keyAlias missing in $KEY_PROPERTIES_FILE"
  [[ -n "${keyPassword:-}" ]] || fail "keyPassword missing in $KEY_PROPERTIES_FILE"

  CERT_SHA1="$(keytool -list -v -keystore "$storeFile" -alias "$keyAlias" -storepass "$storePassword" 2>/dev/null | awk -F': ' '/SHA1:/{print $2; exit}')"
  [[ -n "${CERT_SHA1:-}" ]] || fail "Could not read upload certificate SHA1 from $storeFile"
  ok "upload certificate SHA1: $CERT_SHA1"
  note "Google Maps Android restriction must allow package $PACKAGE_ID with this SHA1."
  note "If Play App Signing is enabled, also add the Play Console app-signing SHA1 for $PACKAGE_ID."
}

load_maps_key() {
  step "Maps key"
  MAPS_API_KEY_VALUE="${MAPS_API_KEY:-${GOOGLE_MAPS_API_KEY:-}}"
  if [[ -z "$MAPS_API_KEY_VALUE" && -f "$SECURE_DIR/${ROLE}_maps_api_key" ]]; then
    MAPS_API_KEY_VALUE="$(sed -n '1p' "$SECURE_DIR/${ROLE}_maps_api_key" | tr -d '\r\n')"
  fi
  if [[ -z "$MAPS_API_KEY_VALUE" && -f "$SECURE_DIR/maps_api_key" ]]; then
    MAPS_API_KEY_VALUE="$(sed -n '1p' "$SECURE_DIR/maps_api_key" | tr -d '\r\n')"
  fi
  if [[ -z "$MAPS_API_KEY_VALUE" ]]; then
    fail "MAPS_API_KEY missing. Export MAPS_API_KEY or put it in $SECURE_DIR/maps_api_key. It must allow package $PACKAGE_ID and SHA1 $CERT_SHA1; if Play App Signing is enabled, add the Play app-signing SHA1 too."
  fi
  export MAPS_API_KEY="$MAPS_API_KEY_VALUE"
  ok "MAPS_API_KEY present (value hidden)"
}

copy_mobile_workspace() {
  step "Copy release build workspace"
  mkdir -p "$BUILD_REPO/mobile/apps" "$BUILD_REPO/mobile/packages"
  COPY_EXCLUDES=(
    --exclude='build/'
    --exclude='.dart_tool/'
    --exclude='.gradle/'
    --exclude='Pods/'
    --exclude='DerivedData/'
    --exclude='.build_logs/'
    --exclude='.git/'
    --exclude='.idea/'
    --exclude='node_modules/'
    --exclude='*.iml'
    --exclude='*.swp'
    --exclude='.DS_Store'
  )

  rsync -a --delete "${COPY_EXCLUDES[@]}" \
    "$ORIGINAL_REPO/mobile/apps/$APP/" \
    "$BUILD_REPO/mobile/apps/$APP/"

  for pkg in auth core localization maps network realtime rides ui_kit; do
    rsync -a --delete "${COPY_EXCLUDES[@]}" \
      "$ORIGINAL_REPO/mobile/packages/$pkg/" \
      "$BUILD_REPO/mobile/packages/$pkg/"
  done

  mkdir -p "$BUILD_APP_DIR/android"
  cat > "$BUILD_APP_DIR/android/key.properties" <<EOF_KEY
storeFile=$storeFile
storePassword=$storePassword
keyAlias=$keyAlias
keyPassword=$keyPassword
EOF_KEY
  chmod 600 "$BUILD_APP_DIR/android/key.properties" 2>/dev/null || true

  if [[ -d "${ANDROID_HOME:-}" ]]; then
    cat > "$BUILD_APP_DIR/android/local.properties" <<EOF_LOCAL
sdk.dir=$ANDROID_HOME
flutter.sdk=$(dirname "$(dirname "$(command -v flutter)")")
flutter.buildMode=release
flutter.versionName=$VERSION_NAME_VALUE
flutter.versionCode=$VERSION_CODE_VALUE
EOF_LOCAL
  fi
  ok "workspace: $BUILD_REPO"
}

build_bundle() {
  step "Build production AAB"
  BUILD_TIMESTAMP="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  GIT_COMMIT="$(git -C "$ORIGINAL_REPO" rev-parse --short HEAD 2>/dev/null || echo unknown)"

  cd "$BUILD_APP_DIR"
  flutter pub get

  flutter build appbundle --release \
    "${FLUTTER_FLAVOR_ARGS[@]}" \
    --target lib/main_prod.dart \
    --build-name "$VERSION_NAME_VALUE" \
    --build-number "$VERSION_CODE_VALUE" \
    --dart-define="APP_ENV=production" \
    --dart-define="RIDE360_RELEASE_BUILD=true" \
    --dart-define="DEV_BYPASS_ENABLED=false" \
    --dart-define="API_BASE_URL=$API_BASE_URL_VALUE" \
    --dart-define="WS_URL=wss://ride.365sakartvelo.com" \
    --dart-define="MAPS_API_KEY=$MAPS_API_KEY_VALUE" \
    --dart-define="HANGOVER_BUILD_APP_NAME=$APP_NAME" \
    --dart-define="HANGOVER_BUILD_VERSION_NAME=$VERSION_NAME_VALUE" \
    --dart-define="HANGOVER_BUILD_VERSION_CODE=$VERSION_CODE_VALUE" \
    --dart-define="HANGOVER_BUILD_TIMESTAMP=$BUILD_TIMESTAMP" \
    --dart-define="HANGOVER_BUILD_PACKAGE_ID=$PACKAGE_ID" \
    --dart-define="HANGOVER_BUILD_COMMIT=$GIT_COMMIT" \
    --dart-define="WS_KEY=${WS_KEY:-}" \
    --dart-define="SENTRY_DSN=${SENTRY_DSN:-}"

  BUILT_AAB="$BUILD_APP_DIR/$EXPECTED_AAB_REL"
  [[ -f "$BUILT_AAB" ]] || fail "Expected AAB not found: $BUILT_AAB"
  ok "built: $BUILT_AAB"
}

verify_and_copy() {
  step "Verify and copy"
  jarsigner -verify -certs "$BUILT_AAB" >/dev/null || fail "AAB signature verification failed"
  ok "AAB signature verified"

  strings_file="$(mktemp)"
  unzip -p "$BUILT_AAB" 'base/lib/*/libapp.so' 2>/dev/null | strings > "$strings_file" || true
  if rg -q '10\.0\.2\.2|127\.0\.0\.1:8000|api\.hangover|api\.staging|https://ride\.365sakartvelo\.com/api/v1|http://ride\.365sakartvelo\.com' "$strings_file"; then
    rg -n '10\.0\.2\.2|127\.0\.0\.1:8000|api\.hangover|api\.staging|https://ride\.365sakartvelo\.com/api/v1|http://ride\.365sakartvelo\.com' "$strings_file" >&2 || true
    rm -f "$strings_file"
    fail "AAB contains a forbidden API base string"
  fi
  if rg -q 'https://ride\.365sakartvelo\.com' "$strings_file"; then
    ok "API_BASE_URL string present: https://ride.365sakartvelo.com"
  else
    warn "Could not find API_BASE_URL string in native release output; this can happen after AOT optimization."
  fi
  rm -f "$strings_file"

  mkdir -p "$DOWNLOADS_DIR"
  cp -f "$BUILT_AAB" "$DESKTOP_AAB"
  cp -f "$BUILT_AAB" "$DOWNLOAD_AAB"

  SHA256="$(shasum -a 256 "$DESKTOP_AAB" | awk '{print $1}')"
  ok "Desktop: $DESKTOP_AAB"
  ok "Downloads: $DOWNLOAD_AAB"
  ok "SHA256: $SHA256"
}

echo "${B}Ride 360 ${LABEL} production AAB builder${X}"
note "package: $PACKAGE_ID"
note "version: $VERSION_NAME_VALUE ($VERSION_CODE_VALUE)"
note "api: $API_BASE_URL_VALUE"

ensure_signing
load_maps_key
copy_mobile_workspace
build_bundle
verify_and_copy

echo
echo "${G}Done.${X}"
echo "AAB: $DESKTOP_AAB"
echo "Package: $PACKAGE_ID"
echo "Version: $VERSION_NAME_VALUE ($VERSION_CODE_VALUE)"
echo "Upload cert SHA1: $CERT_SHA1"
