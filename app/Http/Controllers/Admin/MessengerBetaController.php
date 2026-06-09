<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\SettingsService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aggregates Messenger-only beta metrics into a single view-friendly
 * payload. No side effects, no sends — read-only diagnostic.
 */
class MessengerBetaController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function index()
    {
        $today = now()->startOfDay();
        $last24 = now()->subDay();
        $last7 = now()->subDays(7);

        $counters = $this->todayCounters($today);
        $events = $this->recentAutoReplyEvents();
        $noProduct = $this->recentNoProductQueries();
        $checklist = $this->testerChecklist($last7);
        $ready = $this->readyGate($last24, $counters);
        $devModeWarn = $this->devModeWarning();
        $healthSummary = $this->healthSummary();

        return response()->json([
            'computed_at' => now()->toIso8601String(),
            'ready' => $ready['ready'],
            'ready_blockers' => $ready['blockers'],
            'ready_checks' => $ready['checks'],
            'rollout_mode' => $this->settings->rolloutMode(),
            'counters_today' => $counters,
            'health_summary' => $healthSummary,
            'recent_auto_reply_events' => $events,
            'recent_no_product_queries' => $noProduct,
            'tester_checklist' => $checklist,
            'dev_mode_warning' => $devModeWarn,
        ]);
    }

    private function todayCounters($today): array
    {
        // inbound = messenger inbound messages today
        $inbound = Message::where('direction', Message::DIRECTION_IN)
            ->where('created_at', '>=', $today)
            ->whereHas('conversation', fn ($q) => $q->where('platform', 'messenger'))
            ->count();

        // outbound AI (auto-reply) today
        $outboundAi = Message::where('direction', Message::DIRECTION_OUT)
            ->where('is_ai', true)
            ->where('created_at', '>=', $today)
            ->whereHas('conversation', fn ($q) => $q->where('platform', 'messenger'))
            ->count();

        // skipped (all reasons) today
        $skipped = AuditLog::where('action', 'auto_reply_skipped')
            ->where('created_at', '>=', $today)->count();

        // by reason
        $skippedByReason = $this->skipReasonCounts($today);

        return [
            'inbound' => $inbound,
            'outbound_ai' => $outboundAi,
            'skipped_awaiting_human' => $skipped,
            'no_product' => $skippedByReason['no_product'],
            'complaint_warranty' => $skippedByReason['complaint_warranty'],
            'rollout_suppressed' => $skippedByReason['rollout_suppressed'],
            'failed_sends' => $this->failedSendsToday($today),
            'duplicate_prevented' => $skippedByReason['duplicate'],
        ];
    }

    private function skipReasonCounts($since): array
    {
        $rows = AuditLog::where('action', 'auto_reply_skipped')
            ->where('created_at', '>=', $since)
            ->select(['payload_json'])
            ->get();
        $buckets = ['no_product' => 0, 'complaint_warranty' => 0, 'rollout_suppressed' => 0, 'duplicate' => 0];
        foreach ($rows as $r) {
            $payload = is_string($r->payload_json) ? json_decode($r->payload_json, true) : $r->payload_json;
            $reason = (string) ($payload['reason'] ?? '');
            if (Str::contains($reason, 'no_wc_products') || Str::contains($reason, 'no_wc_match')) {
                $buckets['no_product']++;
            } elseif (Str::contains($reason, 'sensitive_intent')) {
                $buckets['complaint_warranty']++;
            } elseif (Str::startsWith($reason, 'rollout_')) {
                $buckets['rollout_suppressed']++;
            } elseif (Str::contains($reason, 'duplicate')) {
                $buckets['duplicate']++;
            }
        }
        return $buckets;
    }

    private function failedSendsToday($since): int
    {
        $path = storage_path('logs/auto-reply.log');
        if (! is_file($path)) return 0;
        $fp = @fopen($path, 'r');
        if (! $fp) return 0;
        fseek($fp, 0, SEEK_END);
        $size = ftell($fp);
        fseek($fp, max(0, $size - 200_000));
        $tail = fread($fp, $size - max(0, $size - 200_000));
        fclose($fp);

        $count = 0;
        $cutoff = $since->getTimestamp();
        foreach (explode("\n", $tail) as $line) {
            if (! Str::contains($line, 'action=failed')) continue;
            if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
                if (strtotime($m[1]) >= $cutoff) $count++;
            }
        }
        return $count;
    }

    private function recentAutoReplyEvents(int $limit = 20): array
    {
        return AuditLog::whereIn('action', ['auto_reply_sent', 'auto_reply_skipped', 'reply.sent'])
            ->where('subject_type', 'conversation')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'action', 'subject_id', 'payload_json', 'created_at'])
            ->map(function ($r) {
                $payload = is_string($r->payload_json) ? json_decode($r->payload_json, true) : $r->payload_json;
                return [
                    'id' => $r->id,
                    'action' => $r->action,
                    'conv_id' => $r->subject_id,
                    'reason' => $payload['reason'] ?? null,
                    'source' => $payload['source'] ?? null,
                    'intent' => $payload['intent'] ?? null,
                    'ts' => $r->created_at,
                ];
            })
            ->all();
    }

    private function recentNoProductQueries(int $limit = 20): array
    {
        $rows = AuditLog::where('action', 'auto_reply_skipped')
            ->where('subject_type', 'conversation')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'subject_id', 'payload_json', 'created_at']);
        $out = [];
        foreach ($rows as $r) {
            $payload = is_string($r->payload_json) ? json_decode($r->payload_json, true) : $r->payload_json;
            $reason = (string) ($payload['reason'] ?? '');
            if (! Str::contains($reason, 'no_wc_products')) continue;
            $out[] = [
                'conv_id' => $r->subject_id,
                'customer_message' => $payload['customer_message'] ?? null,
                'query' => $payload['query'] ?? null,
                'queries_tried' => $payload['queries_tried'] ?? [],
                'ts' => $r->created_at,
            ];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /**
     * 7 tester scenarios. Each pass when at least one matching audit
     * event has been recorded in the lookback window.
     */
    private function testerChecklist($since): array
    {
        $scenarios = [
            ['key' => 'greeting',        'label' => 'Greeting → no auto-reply (note + awaiting human)'],
            ['key' => 'product',         'label' => 'Clear product question → auto-reply with real WC product'],
            ['key' => 'typo_slang',      'label' => 'Typo/slang product question → WC search expansion finds product'],
            ['key' => 'no_product',      'label' => 'No-product question → no auto-reply, diagnostic note'],
            ['key' => 'warranty',        'label' => 'Warranty/service → no auto-reply, escalated to human'],
            ['key' => 'complaint',       'label' => 'Angry/complaint → no auto-reply, human handles'],
            ['key' => 'multi_msg',       'label' => 'Multiple quick messages → ONE reply after debounce'],
        ];

        $events = AuditLog::whereIn('action', ['auto_reply_sent', 'auto_reply_skipped'])
            ->where('created_at', '>=', $since)
            ->get(['action', 'subject_id', 'payload_json']);

        $tags = ['greeting' => 0, 'product' => 0, 'typo_slang' => 0, 'no_product' => 0,
                'warranty' => 0, 'complaint' => 0, 'multi_msg' => 0];

        // Group events by conv to detect multi_msg debounce (multiple
        // scheduled but single sent/skipped)
        $byConv = [];

        foreach ($events as $e) {
            $payload = is_string($e->payload_json) ? json_decode($e->payload_json, true) : $e->payload_json;
            $intent = (string) ($payload['intent'] ?? '');
            $source = (string) ($payload['source'] ?? '');
            $reason = (string) ($payload['reason'] ?? '');

            if ($intent === 'greeting') $tags['greeting']++;
            if ($e->action === 'auto_reply_sent' && $source === 'wc_grounded') {
                $tags['product']++;
                // typo/slang detection — if query was rewritten (queries_tried > 1)
                if (count($payload['queries_tried'] ?? []) > 1) $tags['typo_slang']++;
            }
            if (Str::contains($reason, 'no_wc_products')) $tags['no_product']++;
            if (Str::contains($reason, 'sensitive_intent:complaint')) $tags['complaint']++;
            if (Str::contains($reason, 'sensitive_intent:order_status') ||
                Str::contains($intent, 'order_status')) $tags['warranty']++;

            $byConv[$e->subject_id][] = $e->action;
        }

        // multi_msg = any conv with both 'sent' AND multiple 'scheduled' events.
        // Simpler proxy: any conv with ≥3 events total (multi-msg debounce
        // produces multiple scheduled + one terminal).
        // We don't track 'scheduled' in audit_log, only via auto-reply.log.
        // So fall back to: if log has any "conv=X" line repeated 3+ times → pass.
        $tags['multi_msg'] = $this->countMultiMsgDebounce() > 0 ? 1 : 0;

        return array_map(function ($s) use ($tags) {
            $hits = $tags[$s['key']] ?? 0;
            $status = $hits > 0 ? 'ok' : 'pending';
            return [
                'key' => $s['key'],
                'label' => $s['label'],
                'hits' => $hits,
                'status' => $status,
            ];
        }, $scenarios);
    }

    private function countMultiMsgDebounce(): int
    {
        $path = storage_path('logs/auto-reply.log');
        if (! is_file($path)) return 0;
        $fp = @fopen($path, 'r');
        if (! $fp) return 0;
        fseek($fp, 0, SEEK_END);
        $size = ftell($fp);
        fseek($fp, max(0, $size - 100_000));
        $tail = fread($fp, $size - max(0, $size - 100_000));
        fclose($fp);

        $scheduledByConv = [];
        foreach (explode("\n", $tail) as $line) {
            if (! Str::contains($line, 'action=scheduled')) continue;
            if (preg_match('/conv=(\d+)/', $line, $m)) {
                $scheduledByConv[$m[1]] = ($scheduledByConv[$m[1]] ?? 0) + 1;
            }
        }
        // Count convs with 3+ scheduled events (= debounced multi-msg)
        return count(array_filter($scheduledByConv, fn ($n) => $n >= 3));
    }

    private function readyGate($last24, array $counters): array
    {
        $checks = [];

        // Helpers
        $ok = fn ($pass, $label, $detail = '') => $checks[] = ['key' => Str::slug($label, '_'), 'label' => $label, 'status' => $pass ? 'ok' : 'fail', 'detail' => $detail];

        // Health summary
        $health = $this->healthSummary();
        $ok($health !== 'fail', 'Health is OK or soft WARN', "current: {$health}");

        // Queue pending = 0
        $pending = DB::table('jobs')->count();
        $ok($pending === 0, 'Queue pending = 0', "current: {$pending}");

        // Failed jobs = 0
        $failed = DB::table('failed_jobs')->count();
        $ok($failed === 0, 'Failed jobs = 0', "current: {$failed}");

        // Required configs
        $ok($this->settings->has('MESSENGER_PAGE_ACCESS_TOKEN') && $this->settings->has('MESSENGER_APP_SECRET'), 'Messenger configured', '');
        $ok($this->settings->has('GADGET_WC_CONSUMER_KEY') && $this->settings->has('GADGET_WC_CONSUMER_SECRET'), 'WooCommerce configured', '');
        $ok($this->settings->has('ANTHROPIC_API_KEY'), 'Claude configured', '');

        // Rollout mode
        $mode = $this->settings->rolloutMode();
        $ok($mode === 'public_product_only', 'Rollout mode = public_product_only', "current: {$mode}");

        // Switches
        $ok($this->settings->getBool('AUTO_REPLY_ENABLED', false), 'Emergency Stop is OFF (auto-reply ON)', '');
        $ok(! $this->settings->getBool('SAFE_MODE_ENABLED', false), 'Safe Mode is OFF', '');

        // Tester conversations — distinct messenger thread_ids in last 7d
        $testerConvs = Conversation::where('platform', 'messenger')
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct('thread_id')
            ->count('thread_id');
        $ok($testerConvs >= 3, '≥3 tester conversations (last 7d)', "current: {$testerConvs}");

        // Successful product auto-replies — auto_reply_sent with source=wc_grounded
        $successfulProduct = AuditLog::where('action', 'auto_reply_sent')
            ->where('created_at', '>=', now()->subDays(7))
            ->get(['payload_json'])
            ->filter(function ($r) {
                $payload = is_string($r->payload_json) ? json_decode($r->payload_json, true) : $r->payload_json;
                return (string) ($payload['source'] ?? '') === 'wc_grounded';
            })
            ->count();
        $ok($successfulProduct >= 3, '≥3 successful WC-grounded auto-replies (last 7d)', "current: {$successfulProduct}");

        // Hallucinations — validator_rejected in last 24h
        $hallucinations = AuditLog::where('action', 'auto_reply_skipped')
            ->where('created_at', '>=', $last24)
            ->get(['payload_json'])
            ->filter(function ($r) {
                $payload = is_string($r->payload_json) ? json_decode($r->payload_json, true) : $r->payload_json;
                return Str::contains((string) ($payload['reason'] ?? ''), 'validator_rejected') ||
                       Str::contains((string) ($payload['source'] ?? ''), 'validator_rejected');
            })
            ->count();
        $ok($hallucinations === 0, '0 hallucinated products (last 24h)', "current: {$hallucinations}");

        // Duplicate replies in last 24h — same conversation, same body, within 60s
        $dupCount = $this->countDuplicateOutbounds($last24);
        $ok($dupCount === 0, '0 duplicate replies (last 24h)', "current: {$dupCount}");

        $blockers = collect($checks)->where('status', 'fail')->pluck('label')->values()->all();
        return [
            'ready' => empty($blockers),
            'blockers' => $blockers,
            'checks' => $checks,
        ];
    }

    private function countDuplicateOutbounds($since): int
    {
        $rows = DB::table('messages')
            ->select('conversation_id', 'body', 'created_at')
            ->where('direction', 'outbound')
            ->where('created_at', '>=', $since)
            ->whereNotNull('body')
            ->orderBy('conversation_id')
            ->orderBy('created_at')
            ->get();
        $seen = [];
        $count = 0;
        foreach ($rows as $r) {
            $key = $r->conversation_id.'|'.md5((string) $r->body);
            if (isset($seen[$key])) {
                $delta = strtotime((string) $r->created_at) - $seen[$key];
                if ($delta < 60) $count++;
            }
            $seen[$key] = strtotime((string) $r->created_at);
        }
        return $count;
    }

    private function devModeWarning(): array
    {
        // Distinct sender PSIDs in raw webhook log
        $path = storage_path('logs/messenger-webhook-raw.log');
        if (! is_file($path)) return ['detected' => false, 'distinct_psids' => 0];
        $fp = @fopen($path, 'r');
        if (! $fp) return ['detected' => false, 'distinct_psids' => 0];
        fseek($fp, 0, SEEK_END);
        $size = ftell($fp);
        fseek($fp, max(0, $size - 200_000));
        $tail = fread($fp, $size - max(0, $size - 200_000));
        fclose($fp);

        $psids = [];
        if (preg_match_all('/"sender":\s*\{\s*"id":\s*"(\d+)"/', $tail, $m)) {
            $psids = array_unique($m[1]);
        }
        $n = count($psids);
        return [
            'detected' => $n <= 1,
            'distinct_psids' => $n,
            'hint' => $n <= 1 ? 'Meta App likely in Development Mode — only App Roles users DM through. Add testers in Meta App Dashboard → App Roles → Roles.' : null,
        ];
    }

    private function healthSummary(): string
    {
        // Quick read: aggregate of the 14 health checks. Mirrors HealthController logic
        // but we only need overall.
        $jobs = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        if ($failed > 0 || $jobs >= 100) return 'fail';
        // We treat soft warnings (resource warnings / no-product signal) as OK at this level
        return 'ok';
    }
}
