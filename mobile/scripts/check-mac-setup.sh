#!/usr/bin/env bash
#
# check-mac-setup.sh
#
# Verifies the local machine has every toolchain piece needed to build
# Hangover Android APKs. Prints one line per check + the fix command
# for anything missing. Exits non-zero if any required tool is absent.
#
# Use this BEFORE running ./mobile/scripts/build-debug-apk.sh on a
# fresh Mac.
#
# Usage:
#   ./mobile/scripts/check-mac-setup.sh

set -uo pipefail

if [[ -t 1 ]]; then
  G=$'\e[32m'; R=$'\e[31m'; Y=$'\e[33m'; D=$'\e[2m'; X=$'\e[0m'
else
  G='' R='' Y='' D='' X=''
fi

OK=0
FAIL=0

ok()   { printf "  %s✔%s %-22s %s\n" "$G" "$X" "$1" "$2"; OK=$((OK+1)); }
fail() { printf "  %s✖%s %-22s %s\n" "$R" "$X" "$1" "$2"; FAIL=$((FAIL+1)); }
warn() { printf "  %s!%s %-22s %s\n" "$Y" "$X" "$1" "$2"; }
hint() { printf "      %sfix:%s %s\n" "$D" "$X" "$1"; }

echo "Hangover Mac toolchain check"
echo

# --- Flutter -----------------------------------------------------------
if command -v flutter >/dev/null 2>&1; then
  fv="$(flutter --version 2>/dev/null | awk '/^Flutter/{print $2; exit}')"
  ok "flutter"          "v$fv"
else
  fail "flutter"        "not found on PATH"
  hint "Install via https://flutter.dev/docs/get-started/install/macos"
  hint "or: brew install --cask flutter"
fi

# --- Java 17 -----------------------------------------------------------
if command -v java >/dev/null 2>&1; then
  jv="$(java -version 2>&1 | head -1 | sed -E 's/.*"([^"]+)".*/\1/')"
  case "$jv" in
    17.*|17) ok "java"  "$jv" ;;
    *)       warn "java" "$jv  (Gradle works best with 17; 21+ sometimes breaks the AGP we pin)"
             hint "brew install --cask temurin@17 && /usr/libexec/java_home -V" ;;
  esac
else
  fail "java"           "not found"
  hint "brew install --cask temurin@17"
fi

# --- ANDROID_HOME ------------------------------------------------------
if [[ -n "${ANDROID_HOME:-}" && -d "$ANDROID_HOME" ]]; then
  ok "ANDROID_HOME"     "$ANDROID_HOME"
else
  # Try the canonical macOS location.
  default="$HOME/Library/Android/sdk"
  if [[ -d "$default" ]]; then
    warn "ANDROID_HOME"  "not exported (SDK present at $default)"
    hint "Add to ~/.zshrc:"
    hint "  export ANDROID_HOME=\$HOME/Library/Android/sdk"
    hint "  export PATH=\$PATH:\$ANDROID_HOME/platform-tools:\$ANDROID_HOME/cmdline-tools/latest/bin"
  else
    fail "ANDROID_HOME"  "no SDK"
    hint "Install Android Studio from https://developer.android.com/studio"
    hint "or:  brew install --cask android-studio"
    hint "Then open it once → SDK Manager → install:"
    hint "    - Android SDK Platform 34"
    hint "    - Android SDK Build-Tools 34.x"
    hint "    - Android SDK Command-line Tools (latest)"
    hint "    - Android SDK Platform-Tools"
  fi
fi

# --- adb ---------------------------------------------------------------
if command -v adb >/dev/null 2>&1; then
  ok "adb"              "$(adb --version | head -1 | awk '{print $1,$2,$3,$4,$5}')"
else
  fail "adb"            "not found on PATH"
  hint "Add platform-tools to PATH (see ANDROID_HOME hint above)"
fi

# --- sdkmanager + licenses --------------------------------------------
sdkmanager=""
for p in "$ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager" \
         "$ANDROID_HOME/tools/bin/sdkmanager" \
         "$(command -v sdkmanager 2>/dev/null)"; do
  if [[ -x "$p" ]]; then sdkmanager="$p"; break; fi
done

if [[ -n "$sdkmanager" ]]; then
  ok "sdkmanager"       "$sdkmanager"
else
  warn "sdkmanager"     "not found (only needed if flutter doctor flags missing licenses)"
fi

# --- flutter doctor short summary -------------------------------------
if command -v flutter >/dev/null 2>&1; then
  echo
  echo "flutter doctor (summary):"
  flutter doctor 2>/dev/null | grep -E '^\[' | sed 's/^/  /'
  echo
  echo "If 'Android toolchain' isn't green, run:"
  echo "  flutter doctor --android-licenses"
  echo "  flutter doctor -v"
fi

# --- repo bootstrap ----------------------------------------------------
if command -v melos >/dev/null 2>&1; then
  ok "melos"            "$(melos --version 2>/dev/null | head -1)"
else
  warn "melos"          "not found"
  hint "dart pub global activate melos"
  hint "and add ~/.pub-cache/bin to PATH"
fi

# --- summary -----------------------------------------------------------
echo
if (( FAIL == 0 )); then
  echo "${G}All required tools present.${X}  Ready to build:"
  echo
  echo "  ./mobile/scripts/build-debug-apk.sh"
  exit 0
else
  echo "${R}Missing $FAIL required tool(s).${X} Fix the items marked ✖ above, then re-run this script."
  exit 1
fi
