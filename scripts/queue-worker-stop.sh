#!/usr/bin/env bash
set -euo pipefail

SHARED_DIR="${RIDE360_SHARED_DIR:-/home/gadgetge/hangover/shared}"
PID_FILE="$SHARED_DIR/queue-worker.pid"
LOCK_FILE="$SHARED_DIR/queue-worker.lock"
MATCH_PATTERN="artisan queue:work database.*--queue=realtime,default"
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"

exec 9>"$LOCK_FILE"
flock 9

stop_pid() {
  local pid="$1"
  if [[ ! "$pid" =~ ^[0-9]+$ ]] || ! kill -0 "$pid" 2>/dev/null; then
    return
  fi

  echo "$(date -Is) stopping queue worker pid=$pid"
  kill "$pid" 2>/dev/null || true
  sleep 2
  if kill -0 "$pid" 2>/dev/null; then
    echo "$(date -Is) queue worker still running pid=$pid; sending TERM again"
    kill "$pid" 2>/dev/null || true
  fi
}

is_worker_pid() {
  local pid="$1"
  local args
  args="$(ps -p "$pid" -o args= 2>/dev/null || true)"
  [[ "$args" == "$PHP_BIN artisan queue:work database "* && "$args" == *"--queue=realtime,default"* ]]
}

if [[ -f "$PID_FILE" ]]; then
  stop_pid "$(cat "$PID_FILE" 2>/dev/null || true)"
  rm -f "$PID_FILE"
fi

for pid in $(pgrep -f "artisan queue:work database" 2>/dev/null || true); do
  is_worker_pid "$pid" || continue
  stop_pid "$pid"
done

echo "$(date -Is) queue worker stopped"
