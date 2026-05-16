<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsRollup;
use App\Models\Conversation;
use App\Models\Escalation;
use App\Models\Message;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function rollupHour(CarbonImmutable $hour): void
    {
        $start = $hour->startOfHour();
        $end   = $hour->endOfHour();

        foreach (['whatsapp', 'messenger', 'instagram', 'facebook'] as $platform) {
            $payload = [
                'day'                    => $start->toDateString(),
                'hour'                   => $start->hour,
                'platform'               => $platform,
                'conversations_started'  => Conversation::where('platform', $platform)->whereBetween('created_at', [$start, $end])->count(),
                'messages_inbound'       => Message::whereBetween('created_at', [$start, $end])->where('direction', 'inbound')->whereHas('conversation', fn ($q) => $q->where('platform', $platform))->count(),
                'messages_outbound_ai'   => Message::whereBetween('created_at', [$start, $end])->where('direction', 'outbound')->where('is_ai', true)->whereHas('conversation', fn ($q) => $q->where('platform', $platform))->count(),
                'messages_outbound_human'=> Message::whereBetween('created_at', [$start, $end])->where('direction', 'outbound')->where('is_ai', false)->whereHas('conversation', fn ($q) => $q->where('platform', $platform))->count(),
                'escalations'            => Escalation::whereBetween('created_at', [$start, $end])->whereHas('conversation', fn ($q) => $q->where('platform', $platform))->count(),
                'orders_created'         => Order::whereBetween('created_at', [$start, $end])->whereHas('conversation', fn ($q) => $q->where('platform', $platform))->count(),
                'orders_paid'            => Order::whereBetween('paid_at', [$start, $end])->whereHas('conversation', fn ($q) => $q->where('platform', $platform))->count(),
                'comments_handled'       => DB::table('comments')->where('platform', $platform)->where('replied', true)->whereBetween('updated_at', [$start, $end])->count(),
                'avg_response_seconds'   => $this->avgResponseSeconds($platform, $start, $end),
            ];

            AnalyticsRollup::updateOrCreate(
                ['day' => $payload['day'], 'hour' => $payload['hour'], 'platform' => $platform],
                $payload,
            );
        }
    }

    public function dashboard(): array
    {
        $today = now()->toDateString();
        $rows  = AnalyticsRollup::whereDate('day', $today)->get();

        return [
            'date'                   => $today,
            'total_conversations'    => (int) $rows->sum('conversations_started'),
            'inbound'                => (int) $rows->sum('messages_inbound'),
            'ai_replies'             => (int) $rows->sum('messages_outbound_ai'),
            'human_replies'          => (int) $rows->sum('messages_outbound_human'),
            'escalations'            => (int) $rows->sum('escalations'),
            'orders_created'         => (int) $rows->sum('orders_created'),
            'orders_paid'            => (int) $rows->sum('orders_paid'),
            'comments_handled'       => (int) $rows->sum('comments_handled'),
            'ai_share'               => $this->ratio((int) $rows->sum('messages_outbound_ai'), (int) $rows->sum('messages_outbound_ai') + (int) $rows->sum('messages_outbound_human')),
            'by_platform'            => $rows->groupBy('platform')->map(fn ($p) => [
                'conversations' => (int) $p->sum('conversations_started'),
                'inbound'       => (int) $p->sum('messages_inbound'),
                'escalations'   => (int) $p->sum('escalations'),
                'orders'        => (int) $p->sum('orders_created'),
            ])->all(),
        ];
    }

    private function ratio(int $a, int $total): float
    {
        return $total > 0 ? round($a / $total, 3) : 0.0;
    }

    private function avgResponseSeconds(string $platform, CarbonImmutable $start, CarbonImmutable $end): ?float
    {
        $rows = DB::table('messages as out')
            ->join('conversations as c', 'c.id', '=', 'out.conversation_id')
            ->where('c.platform', $platform)
            ->where('out.direction', 'outbound')
            ->whereBetween('out.created_at', [$start, $end])
            ->selectRaw('out.conversation_id, out.created_at as out_at, (
                SELECT MAX(m.created_at) FROM messages m
                WHERE m.conversation_id = out.conversation_id
                  AND m.direction = "inbound"
                  AND m.created_at < out.created_at
            ) as in_at')
            ->limit(500)
            ->get();

        $diffs = $rows->filter(fn ($r) => $r->in_at)->map(fn ($r) => strtotime($r->out_at) - strtotime($r->in_at));
        if ($diffs->isEmpty()) {
            return null;
        }
        return round($diffs->avg(), 2);
    }
}
