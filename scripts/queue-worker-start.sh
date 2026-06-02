#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${RIDE360_BACKEND_DIR:-/home/gadgetge/hangover/current/backend}"
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
SHARED_DIR="${RIDE360_SHARED_DIR:-/home/gadgetge/hangover/shared}"
LOG_DIR="$SHARED_DIR/storage/logs"
LOG_FILE="$LOG_DIR/queue-worker.log"
PID_FILE="$SHARED_DIR/queue-worker.pid"
LOCK_FILE="$SHARED_DIR/queue-worker.lock"
QUEUE_ARGS=(queue:work database --queue=realtime,default --sleep=1 --tries=3 --timeout=60 --memory=128)
MATCH_PATTERN="artisan queue:work database.*--queue=realtime,default"

mkdir -p "$LOG_DIR"
touch "$LOG_FILE"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "$(date -Is) queue worker start skipped: lock held"
  exit 0
fi

current_backend="$(readlink -f "$APP_DIR")"

is_worker_pid() {
  local pid="$1"
  ps -p "$pid" -o args= 2>/dev/null | grep -Eq "$MATCH_PATTERN"
}

pid_cwd() {
  local pid="$1"
  readlink -f "/proc/$pid/cwd" 2>/dev/null || true
}

stop_old_worker() {
  local pid="$1"
  echo "$(date -Is) stopping old queue worker pid=$pid"
  kill "$pid" 2>/dev/null || true
  sleep 2
  if kill -0 "$pid" 2>/dev/null; then
    echo "$(date -Is) old queue worker still running pid=$pid; sending TERM again"
    kill "$pid" 2>/dev/null || true
  fi
}

if [[ -f "$PID_FILE" ]]; then
  pid="$(cat "$PID_FILE" 2>/dev/null || true)"
  if [[ "$pid" =~ ^[0-9]+$ ]] && kill -0 "$pid" 2>/dev/null && is_worker_pid "$pid"; then
    worker_cwd="$(pid_cwd "$pid")"
    if [[ "$worker_cwd" == "$current_backend" ]]; then
      echo "$(date -Is) queue worker already running pid=$pid"
      exit 0
    fi

    stop_old_worker "$pid"
  fi
fi

while IFS= read -r pid; do
  [[ -z "$pid" ]] && continue
  worker_cwd="$(pid_cwd "$pid")"
  if [[ "$worker_cwd" == "$current_backend" ]]; then
    echo "$pid" > "$PID_FILE"
    echo "$(date -Is) queue worker already running pid=$pid"
    exit 0
  fi

  if [[ -n "$worker_cwd" ]]; then
    stop_old_worker "$pid"
  fi
done < <(pgrep -f "$MATCH_PATTERN" 2>/dev/null || true)

cd "$APP_DIR"
nohup "$PHP_BIN" artisan "${QUEUE_ARGS[@]}" >> "$LOG_FILE" 2>&1 &
pid="$!"
echo "$pid" > "$PID_FILE"
echo "$(date -Is) queue worker started pid=$pid cwd=$current_backend log=$LOG_FILE"
