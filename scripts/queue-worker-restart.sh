#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"$SCRIPT_DIR/queue-worker-stop.sh"
"$SCRIPT_DIR/queue-worker-start.sh"
