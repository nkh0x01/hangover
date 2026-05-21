#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IOS_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_DIR="$(cd "$IOS_DIR/.." && pwd)"
REPO_ROOT="${CI_PRIMARY_REPOSITORY_PATH:-$(cd "$APP_DIR/../../.." && pwd)}"
HELPER="$SCRIPT_DIR/ride360_xcode_cloud.sh"
[[ -x "$HELPER" ]] || HELPER="$REPO_ROOT/mobile/scripts/xcode_cloud/ride360_xcode_cloud.sh"

exec "$HELPER" pre_xcodebuild customer "$APP_DIR"
