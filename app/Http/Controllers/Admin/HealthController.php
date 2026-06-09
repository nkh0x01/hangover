<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bot health diagnostics shown in the dashboard widget + dedicated
 * /admin/health page. All checks are cheap (single SQL or single
 * config read) so the endpoint can be polled every 15s safely.
 */
class HealthController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function check(Request $request)
    {
        $now = now();
        $checks = [
            $this->rolloutMode(),
            $this->masterToggle(),
            $this->safeMode(),
            $this->todayCounters($now),
            $this->cronFreshness($now),
            $this->queueDepth(),
            $this->failedJobs($now),
            $this->resourceWarnings(),
            $this->lastFatalError(),
            $this->aiConfig(),
            $this->wcConfig(),
            $this->messengerConfig(),
            $this->lastAutoReply($now),
            $this->lastNoProduct($now),
        ];
        $overall = $this->aggregateStatus($checks);
        return response()->json([
            'status' => $overall,
            'computed_at' => $now->toIso8601String(),
            'checks' => $checks,
        ]);
    }

    /** Toggle helper used by the Emergency Stop banner. */
    public function toggleMaster(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $this->settings->set('AUTO_REPLY_ENABLED', $request->boolean('enabled') ? 'true' : 'false', 'auto_reply');
        AuditLog::record('employee', 'auto_reply.master_toggle', null, null, [
            'enabled' => $request->boolean('enabled'),
        ], $request->user()?->id);
        return response()->json([
            'ok' => true,
            'enabled' => $request->boolean('enabled'),
        ]);
    }

    /** Safe Mode toggle — independent kill-switch, preserves AUTO_REPLY_ENABLED. */
    public function toggleSafeMode(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $this->settings->set('SAFE_MODE_ENABLED', $request->boolean('enabled') ? 'true' : 'false', 'auto_reply');
        AuditLog::record('employee', 'safe_mode.toggle', null, null, [
            'enabled' => $request->boolean('enabled'),
        ], $request->user()?->id);
        return response()->json([
            'ok' => true,
            'safe_mode' => $request->boolean('enabled'),
            // report the underlying auto-reply setting so UI can show "will resume to X"
            'auto_reply_setting' => $this->settings->getBool('AUTO_REPLY_ENABLED', false),
        ]);
    }

    // ===== individual checks =====

    private function masterToggle(): array
    {
        $on = $this->settings->getBool('AUTO_REPLY_ENABLED', false);
        return $this->item('master_toggle', 'Auto-reply master', $on ? 'ok' : 'warn',
            $on ? 'ON' : 'OFF — bot will not auto-reply',
            $on ? null : 'Click the toggle on the dashboard to enable',
        );
    }

    private function rolloutMode(): array
    {
        $mode = $this->settings->rolloutMode();
        $labels = [
            'beta' => 'BETA — auto-reply product + general (internal)',
            'public_receive_only' => 'PUBLIC RECEIVE-ONLY — no auto-send, all to humans',
            'public_product_only' => 'PUBLIC PRODUCT-ONLY — auto-reply only WC-grounded products',
        ];
        // public_product_only and public_receive_only are "safe public" → ok.
        // beta is fine too (internal). All are ok-status; this row is informational.
        return $this->item('rollout_mode', 'Rollout mode', 'ok',
            $labels[$mode] ?? $mode);
    }

    private function todayCounters($now): array
    {
        $startOfDay = $now->copy()->startOfDay();
        $sent = AuditLog::where('action', 'auto_reply_sent')->where('created_at', '>=', $startOfDay)->count();
        $skipped = AuditLog::where('action', 'auto_reply_skipped')->where('created_at', '>=', $startOfDay)->count();
        return $this->item('today_counters', 'Today (auto-reply)', 'ok',
            "{$sent} sent · {$skipped} skipped/awaiting-human");
    }

    private function safeMode(): array
    {
        $safe = $this->settings->getBool('SAFE_MODE_ENABLED', false);
        if (! $safe) {
            return $this->item('safe_mode', 'Safe Mode', 'ok', 'OFF (normal operation)');
        }
        return $this->item('safe_mode', 'Safe Mode', 'warn', 'ON — auto-reply suppressed, inbox + manual reply active',
            'turn off from dashboard to resume auto-reply');
    }

    /**
     * Scan laravel.log for hosting resource warnings (CloudLinux nproc /
     * EP starvation). Counts last-24h occurrences. NOT a failure unless the
     * queue is actually stuck — these are intermittent and self-heal.
     */
    private function resourceWarnings(): array
    {
        $patterns = ['posix_spawn', 'getaddrinfo', 'Resource temporarily unavailable', 'proc_open'];
        $count = $this->countLogMatches($patterns, 86400);
        if ($count === 0) {
            return $this->item('resource_warnings', 'Hosting resources', 'ok', 'no nproc/spawn warnings in 24h');
        }
        // Soft warn only — queue health is the real signal
        $queueStuck = DB::table('jobs')->count() >= 100;
        $status = $queueStuck ? 'fail' : 'warn';
        return $this->item('resource_warnings', 'Hosting resources', $status,
            "{$count} spawn/nproc warning(s) in 24h",
            'intermittent CloudLinux limit — ask hosting to raise nproc / Entry Processes (EP). Bot self-heals next tick.');
    }

    /**
     * Surface the most recent PHP fatal / route / config error from
     * laravel.log so a 503-class regression is visible at a glance.
     */
    private function lastFatalError(): array
    {
        $needle = ['FatalError', 'Fatal error', 'already in use', 'syntax error', 'Class \"', 'Cannot use'];
        $hit = $this->lastLogMatch($needle, 86400);
        if (! $hit) {
            return $this->item('last_fatal', 'Last fatal error', 'ok', 'none in last 24h');
        }
        return $this->item('last_fatal', 'Last fatal error', 'warn',
            mb_strimwidth($hit['line'], 0, 90, '…'),
            'resolved if site is up — run `php artisan bot:preflight` to confirm');
    }

    /** Count laravel.log lines matching any needle within $withinSeconds. */
    private function countLogMatches(array $needles, int $withinSeconds): int
    {
        $lines = $this->tailLog(200_000);
        if (! $lines) return 0;
        $cutoff = now()->getTimestamp() - $withinSeconds;
        $count = 0;
        foreach ($lines as $line) {
            if (! $this->lineWithin($line, $cutoff)) continue;
            foreach ($needles as $n) {
                if (str_contains($line, $n)) { $count++; break; }
            }
        }
        return $count;
    }

    private function lastLogMatch(array $needles, int $withinSeconds): ?array
    {
        $lines = $this->tailLog(200_000);
        if (! $lines) return null;
        $cutoff = now()->getTimestamp() - $withinSeconds;
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            foreach ($needles as $n) {
                if (str_contains($line, $n) && $this->lineWithin($line, $cutoff)) {
                    return ['line' => trim($line)];
                }
            }
        }
        return null;
    }

    private function lineWithin(string $line, int $cutoffTs): bool
    {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
            return strtotime($m[1]) >= $cutoffTs;
        }
        return true; // no timestamp (stacktrace line) — keep
    }

    private function tailLog(int $bytes): array
    {
        $path = storage_path('logs/laravel.log');
        if (! is_file($path)) return [];
        $fp = @fopen($path, 'r');
        if (! $fp) return [];
        fseek($fp, 0, SEEK_END);
        $size = ftell($fp);
        $offset = max(0, $size - $bytes);
        fseek($fp, $offset);
        $data = fread($fp, $size - $offset);
        fclose($fp);
        return explode("\n", $data);
    }

    private function cronFreshness($now): array
    {
        $tickLog = storage_path('logs/tick.log');
        if (! is_file($tickLog)) {
            return $this->item('cron', 'Cron tick', 'fail', 'tick.log missing — cron not running yet?');
        }
        $mtime = filemtime($tickLog);
        $delta = $now->getTimestamp() - $mtime;
        if ($delta > 180) {
            return $this->item('cron', 'Cron tick', 'fail', "last tick {$delta}s ago", 'cPanel cron may be stopped');
        }
        if ($delta > 90) {
            return $this->item('cron', 'Cron tick', 'warn', "last tick {$delta}s ago");
        }
        return $this->item('cron', 'Cron tick', 'ok', "last tick {$delta}s ago");
    }

    private function queueDepth(): array
    {
        $pending = DB::table('jobs')->count();
        if ($pending === 0) return $this->item('queue_depth', 'Queue depth', 'ok', '0 pending');
        if ($pending < 20) return $this->item('queue_depth', 'Queue depth', 'ok', "{$pending} pending");
        if ($pending < 100) return $this->item('queue_depth', 'Queue depth', 'warn', "{$pending} pending");
        return $this->item('queue_depth', 'Queue depth', 'fail', "{$pending} pending", 'queue worker may be stuck');
    }

    private function failedJobs($now): array
    {
        $count = DB::table('failed_jobs')->count();
        $last24h = DB::table('failed_jobs')->where('failed_at', '>=', $now->copy()->subDay())->count();
        if ($count === 0) return $this->item('failed_jobs', 'Failed jobs', 'ok', 'none');
        if ($last24h === 0) return $this->item('failed_jobs', 'Failed jobs', 'warn', "{$count} historical", 'safe to clear');
        return $this->item('failed_jobs', 'Failed jobs', 'fail', "{$count} total, {$last24h} in last 24h");
    }

    private function aiConfig(): array
    {
        if (! $this->settings->has('ANTHROPIC_API_KEY')) {
            return $this->item('ai_config', 'Claude AI key', 'fail', 'ANTHROPIC_API_KEY not set', 'Integrations → AI');
        }
        return $this->item('ai_config', 'Claude AI key', 'ok', 'configured');
    }

    private function wcConfig(): array
    {
        $required = ['GADGET_WC_BASE_URL', 'GADGET_WC_CONSUMER_KEY', 'GADGET_WC_CONSUMER_SECRET'];
        $missing = array_filter($required, fn ($k) => ! $this->settings->has($k));
        if ($missing) {
            return $this->item('wc_config', 'WooCommerce', 'fail', 'missing: '.implode(',', $missing), 'Integrations → WooCommerce');
        }
        return $this->item('wc_config', 'WooCommerce', 'ok', 'configured');
    }

    private function messengerConfig(): array
    {
        $required = ['MESSENGER_PAGE_ID', 'MESSENGER_PAGE_ACCESS_TOKEN', 'MESSENGER_APP_SECRET', 'MESSENGER_VERIFY_TOKEN'];
        $set = collect($required)->filter(fn ($k) => $this->settings->has($k))->count();
        if ($set === count($required)) return $this->item('messenger_config', 'Messenger', 'ok', 'all 4 fields set');
        if ($set === 0) return $this->item('messenger_config', 'Messenger', 'fail', 'not configured');
        return $this->item('messenger_config', 'Messenger', 'warn', "{$set}/4 fields set");
    }

    /**
     * Most recent "no WC product" skip — surfaces the customer's
     * unanswered query so the team can see catalog gaps at a glance.
     * NOT a failure: this is a business signal, not a system error.
     */
    private function lastNoProduct($now): array
    {
        $row = AuditLog::where('action', 'auto_reply_skipped')
            ->orderByDesc('id')
            ->take(50) // only scan recent
            ->get(['id', 'subject_id', 'payload_json', 'created_at']);
        $hit = null;
        foreach ($row as $r) {
            $payload = is_string($r->payload_json) ? json_decode($r->payload_json, true) : $r->payload_json;
            $reason = (string) ($payload['reason'] ?? '');
            if (str_contains($reason, 'no_wc_products') || str_contains($reason, 'no_wc_match')) {
                $hit = $r;
                break;
            }
        }
        if (! $hit) {
            return $this->item('last_no_product', 'Last no-product query', 'ok', 'none in last 50 events');
        }
        $payload = is_string($hit->payload_json) ? json_decode($hit->payload_json, true) : $hit->payload_json;
        $delta = (int) round($now->diffInMinutes($hit->created_at));
        $custMsg = mb_strimwidth((string) ($payload['customer_message'] ?? '?'), 0, 40, '…');
        $query = (string) ($payload['query'] ?? '?');
        $msg = "{$delta}m ago · \"{$custMsg}\" → \"{$query}\" · conv #{$hit->subject_id}";
        // status = "ok" — catalog gap is a business signal, not a system failure.
        // The message text + hint surfaces the catalog-improvement opportunity
        // without dragging the overall health badge to warn.
        return $this->item('last_no_product', 'Last no-product query', 'ok', $msg,
            'business signal — review KeywordMapper synonyms or add to WC catalog');
    }

    private function lastAutoReply($now): array
    {
        $last = AuditLog::whereIn('action', ['auto_reply_sent', 'auto_reply_skipped'])
            ->orderByDesc('id')
            ->first(['action', 'payload_json', 'created_at']);
        if (! $last) {
            return $this->item('last_auto_reply', 'Last auto-reply event', 'pending', 'no events yet');
        }
        $delta = $now->diffInMinutes($last->created_at);
        $payload = is_string($last->payload_json) ? json_decode($last->payload_json, true) : $last->payload_json;
        $detail = $last->action.' · '.($payload['reason'] ?? $payload['source'] ?? '?');
        return $this->item('last_auto_reply', 'Last auto-reply event', 'ok', "{$delta}m ago: {$detail}");
    }

    private function item(string $key, string $title, string $status, string $message, ?string $hint = null): array
    {
        return compact('key', 'title', 'status', 'message', 'hint');
    }

    private function aggregateStatus(array $checks): string
    {
        foreach ($checks as $c) if ($c['status'] === 'fail') return 'fail';
        foreach ($checks as $c) if ($c['status'] === 'warn') return 'warn';
        return 'ok';
    }
}
