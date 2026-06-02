#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${RIDE360_BACKEND_DIR:-/home/gadgetge/hangover/current/backend}"
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
SHARED_DIR="${RIDE360_SHARED_DIR:-/home/gadgetge/hangover/shared}"
LOG_FILE="$SHARED_DIR/storage/logs/queue-worker.log"
PID_FILE="$SHARED_DIR/queue-worker.pid"
MATCH_PATTERN="artisan queue:work database.*--queue=realtime,default"
worker_pids() {
  for pid in $(pgrep -f "artisan queue:work database" 2>/dev/null || true); do
    args="$(ps -p "$pid" -o args= 2>/dev/null || true)"
    if [[ "$args" == "$PHP_BIN artisan queue:work database "* && "$args" == *"--queue=realtime,default"* ]]; then
      echo "$pid $args"
    fi
  done
}

echo "backend=$(readlink -f "$APP_DIR")"
echo "php=$PHP_BIN"
echo "log=$LOG_FILE"

if [[ -f "$PID_FILE" ]]; then
  pid="$(cat "$PID_FILE" 2>/dev/null || true)"
  echo "pid_file=$pid"
  if [[ "$pid" =~ ^[0-9]+$ ]] && kill -0 "$pid" 2>/dev/null; then
    echo "pid_file_running=yes"
    echo "pid_file_cwd=$(readlink -f "/proc/$pid/cwd" 2>/dev/null || true)"
  else
    echo "pid_file_running=no"
  fi
else
  echo "pid_file=missing"
fi

echo "processes:"
worker_pids || true

cd "$APP_DIR"
"$PHP_BIN" artisan tinker --execute='use Illuminate\Support\Facades\DB; echo json_encode([
    "queue_connection" => config("queue.default"),
    "pending_jobs" => DB::table("jobs")->count(),
    "pending_by_queue" => DB::table("jobs")->select("queue", DB::raw("count(*) as count"))->groupBy("queue")->pluck("count", "queue"),
    "failed_jobs" => DB::table("failed_jobs")->count(),
    "latest_failed_at" => DB::table("failed_jobs")->latest("id")->value("failed_at"),
]);'
echo

if [[ -f "$LOG_FILE" ]]; then
  echo "last_log_lines:"
  tail -20 "$LOG_FILE"
fi
