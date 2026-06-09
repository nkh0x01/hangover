<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $sinceParam = $request->input('since', '7d');
        $since = match ($sinceParam) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };

        // Conversations summary by platform
        $convByPlatform = Conversation::query()
            ->where('created_at', '>=', $since)
            ->groupBy('platform')
            ->selectRaw('platform, COUNT(*) AS c')
            ->pluck('c', 'platform')
            ->all();

        // Lead status funnel
        $leadFunnel = Conversation::query()
            ->where('created_at', '>=', $since)
            ->groupBy('lead_status')
            ->selectRaw('lead_status, COUNT(*) AS c')
            ->pluck('c', 'lead_status')
            ->all();

        // Auto-reply counters (24h + period)
        $autoSent24h = AuditLog::where('action', 'auto_reply_sent')
            ->where('created_at', '>=', now()->subDay())->count();
        $autoSentPeriod = AuditLog::where('action', 'auto_reply_sent')
            ->where('created_at', '>=', $since)->count();
        $autoSkippedPeriod = AuditLog::where('action', 'auto_reply_skipped')
            ->where('created_at', '>=', $since)->count();
        $takeovers = AuditLog::where('action', 'takeover')
            ->where('created_at', '>=', $since)->count();
        $manualReplies = AuditLog::where('action', 'manual.reply')
            ->where('created_at', '>=', $since)->count();

        // Top skip reasons (parse payload_json.reason)
        $skipRows = AuditLog::where('action', 'auto_reply_skipped')
            ->where('created_at', '>=', $since)
            ->select(['payload_json'])
            ->get();
        $skipReasons = [];
        foreach ($skipRows as $r) {
            $payload = is_string($r->payload_json) ? json_decode($r->payload_json, true) : $r->payload_json;
            $reason = (string) ($payload['reason'] ?? 'unknown');
            // Trim suffixes like ":...details..."
            $clean = explode(':', $reason)[0];
            $clean = explode('_', $clean);
            // Keep first 2-3 tokens for grouping
            $clean = implode('_', array_slice($clean, 0, 3));
            $skipReasons[$clean] = ($skipReasons[$clean] ?? 0) + 1;
        }
        arsort($skipReasons);
        $topSkipReasons = array_slice($skipReasons, 0, 8, true);

        // Top recommended products (from auto_reply_sent.product_ids array)
        $sentRows = AuditLog::where('action', 'auto_reply_sent')
            ->where('created_at', '>=', $since)
            ->select(['payload_json'])
            ->get();
        $productCounts = [];
        foreach ($sentRows as $r) {
            $payload = is_string($r->payload_json) ? json_decode($r->payload_json, true) : $r->payload_json;
            foreach (($payload['product_ids'] ?? []) as $pid) {
                if (! $pid) continue;
                $productCounts[$pid] = ($productCounts[$pid] ?? 0) + 1;
            }
        }
        arsort($productCounts);
        $topProducts = array_slice($productCounts, 0, 10, true);
        // Resolve product names from products table if synced
        $names = \App\Models\Product::query()
            ->whereIn('source_id', array_map('strval', array_keys($topProducts)))
            ->pluck('name', 'source_id')
            ->all();
        $topProductsResolved = [];
        foreach ($topProducts as $id => $cnt) {
            $topProductsResolved[] = [
                'product_id' => $id,
                'count' => $cnt,
                'name' => $names[(string) $id] ?? '(not synced locally · id '.$id.')',
            ];
        }

        // Outbound message volume (per direction, AI vs human)
        $outboundCounts = Message::query()
            ->where('created_at', '>=', $since)
            ->where('direction', 'outbound')
            ->selectRaw('is_ai, COUNT(*) AS c')
            ->groupBy('is_ai')
            ->pluck('c', 'is_ai')
            ->all();
        $inboundCount = Message::query()
            ->where('created_at', '>=', $since)
            ->where('direction', 'inbound')->count();

        return response()->json([
            'since' => $sinceParam,
            'computed_at' => now()->toIso8601String(),
            'conversations_by_platform' => $convByPlatform,
            'lead_funnel' => $leadFunnel,
            'counters' => [
                'auto_reply_sent_24h' => $autoSent24h,
                'auto_reply_sent_period' => $autoSentPeriod,
                'auto_reply_skipped_period' => $autoSkippedPeriod,
                'takeovers_period' => $takeovers,
                'manual_replies_period' => $manualReplies,
                'inbound_period' => $inboundCount,
                'outbound_ai_period' => (int) ($outboundCounts[1] ?? 0),
                'outbound_human_period' => (int) ($outboundCounts[0] ?? 0),
            ],
            'top_skip_reasons' => $topSkipReasons,
            'top_products' => $topProductsResolved,
        ]);
    }
}
