<?php

namespace App\Services\Inbox;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Note;
use App\Services\SettingsService;

class UnifiedInbox
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Returns current auto-reply state for a conversation:
     *   enabled / reason if off (first blocking gate)
     *   + last_log: most recent line from logs/auto-reply.log for this conv (for "why didn't bot reply?")
     */
    public function autoReplyState(Conversation $c): array
    {
        $reason = null;
        if (! $this->settings->isAutoReplyEnabledFor($c->platform)) {
            $reason = 'channel_disabled';
        } elseif (! $this->settings->isWithinBusinessHours()) {
            $reason = 'outside_business_hours';
        } elseif ($c->ai_paused) {
            $reason = 'ai_paused';
        } elseif ($c->escalated) {
            $reason = 'escalated';
        } elseif ($c->assigned_employee_id) {
            $reason = 'assigned';
        } elseif ($c->customer && ($c->customer->is_spam || $c->customer->is_blocked)) {
            $reason = 'customer_blocked';
        }
        return [
            'enabled' => $reason === null,
            'reason' => $reason,
            'last_log' => $this->lastAutoReplyLogEntry($c->id),
            'last_decision' => $this->lastAiDecision($c->id),
        ];
    }

    /**
     * Pull the most recent AI decision metadata for "Why did AI reply this way?"
     * Looks at audit_logs for action in (auto_reply_sent, auto_reply_skipped,
     * reply.sent). Returns the JSON payload with intent / query / product_ids etc.
     */
    private function lastAiDecision(int $convId): ?array
    {
        $row = AuditLog::where('subject_type', 'conversation')
            ->where('subject_id', $convId)
            ->whereIn('action', ['auto_reply_sent', 'auto_reply_skipped', 'reply.sent', 'ai.suggest', 'product.recommend'])
            ->orderByDesc('id')
            ->first(['id', 'action', 'payload_json', 'created_at']);
        if (! $row) return null;
        return [
            'id' => $row->id,
            'action' => $row->action,
            'meta' => is_string($row->payload_json) ? json_decode($row->payload_json, true) : $row->payload_json,
            'ts' => $row->created_at,
        ];
    }

    /**
     * Find the most recent line in auto-reply.log for this conversation id.
     * Returns ['ts' => ..., 'action' => ..., 'reason' => ..., 'source' => ...]
     */
    private function lastAutoReplyLogEntry(int $convId): ?array
    {
        $path = storage_path('logs/auto-reply.log');
        if (! is_file($path)) return null;
        $fp = @fopen($path, 'r');
        if (! $fp) return null;

        // Read the last 32KB — auto-reply.log is small/growing slowly
        fseek($fp, 0, SEEK_END);
        $size = ftell($fp);
        $offset = max(0, $size - 32_768);
        fseek($fp, $offset);
        $tail = fread($fp, $size - $offset);
        fclose($fp);

        $needle = ' conv='.$convId.' ';
        $lines = explode("\n", $tail);
        // Walk backward, return first match
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            if (! str_contains($line, $needle)) continue;
            // Parse: [TS] conv=X platform=Y thread=Z action=W {json}
            if (preg_match('/^\[([^\]]+)\] conv=\d+ platform=\S+ thread=\S+ action=(\S+) (.+)$/', $line, $m)) {
                $payload = json_decode($m[3], true) ?: [];
                return [
                    'ts' => $m[1],
                    'action' => $m[2],
                    'reason' => $payload['reason'] ?? null,
                    'source' => $payload['source'] ?? null,
                    'error' => $payload['error'] ?? null,
                    'image_sent' => $payload['image_sent'] ?? null,
                ];
            }
        }
        return null;
    }
    public function list(array $filters = [], int $limit = 50): array
    {
        $q = Conversation::query()
            ->with(['customer', 'assignedEmployee'])
            ->orderByDesc('last_inbound_at');

        if (! empty($filters['platform'])) {
            $q->where('platform', $filters['platform']);
        }
        if (! empty($filters['status'])) {
            $q->where('lead_status', $filters['status']);
        }
        if (isset($filters['escalated'])) {
            $q->where('escalated', (bool) $filters['escalated']);
        }
        if (isset($filters['unanswered']) && $filters['unanswered']) {
            $q->whereColumn('last_inbound_at', '>', 'last_outbound_at');
        }
        if (isset($filters['unread']) && $filters['unread']) {
            $q->where(function ($qq) {
                $qq->whereNull('last_read_at')
                    ->orWhereColumn('last_inbound_at', '>', 'last_read_at');
            })->whereNotNull('last_inbound_at');
        }
        if (! empty($filters['q'])) {
            $needle = $filters['q'];
            $q->where(function ($qq) use ($needle) {
                $qq->whereHas('customer', fn ($c) => $c->where('display_name', 'like', "%$needle%")
                    ->orWhere('phone', 'like', "%$needle%")
                    ->orWhere('platform_user_id', 'like', "%$needle%"));
            });
        }

        $rows = $q->limit($limit)->get();

        // Bulk note count per conversation
        $convIds = $rows->pluck('id');
        $noteCounts = Note::whereIn('conversation_id', $convIds)
            ->selectRaw('conversation_id, COUNT(*) AS c')
            ->groupBy('conversation_id')
            ->pluck('c', 'conversation_id');

        return $rows->map(function (Conversation $c) use ($noteCounts) {
            $last = Message::where('conversation_id', $c->id)->latest('id')->first();
            $profile = $c->customer?->profile_json ?? [];
            $unread = $c->last_inbound_at && (! $c->last_read_at || $c->last_inbound_at > $c->last_read_at);

            return [
                'id' => $c->id,
                'platform' => $c->platform,
                'thread_id' => $c->thread_id,
                'lead_status' => $c->lead_status,
                'escalated' => $c->escalated,
                'ai_paused' => $c->ai_paused,
                'unread' => $unread,
                'note_count' => (int) ($noteCounts[$c->id] ?? 0),
                'last_inbound' => $c->last_inbound_at,
                'last_outbound' => $c->last_outbound_at,
                'last_read' => $c->last_read_at,
                'last_message' => $last ? [
                    'direction' => $last->direction,
                    'body' => mb_substr((string) $last->body, 0, 200),
                    'is_ai' => (bool) $last->is_ai,
                    'created_at' => $last->created_at,
                ] : null,
                'customer' => [
                    'id' => $c->customer?->id,
                    'name' => $c->customer?->display_name,
                    'handle' => $c->customer?->platform_user_id,
                    'is_vip' => (bool) $c->customer?->is_vip,
                    'profile_pic' => $profile['profile_pic'] ?? null,
                    'memory' => $profile,
                ],
                'assigned' => $c->assignedEmployee?->only(['id', 'name']),
            ];
        })->all();
    }

    public function thread(int $conversationId, int $limit = 200): array
    {
        $c = Conversation::with(['customer', 'assignedEmployee'])->findOrFail($conversationId);
        $messages = Message::where('conversation_id', $c->id)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'kind' => $m->kind,
                'body' => $m->body,
                'media' => $m->media_json,
                'is_ai' => (bool) $m->is_ai,
                'confidence' => $m->confidence,
                'intent' => $m->intent,
                'author' => $m->author?->only(['id', 'name']),
                'created_at' => $m->created_at,
            ])
            ->all();

        $notes = Note::where('conversation_id', $c->id)
            ->with('employee:id,name')
            ->orderByDesc('pinned')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Note $n) => [
                'id' => $n->id,
                'body' => $n->body,
                'pinned' => (bool) $n->pinned,
                'employee' => $n->employee?->only(['id', 'name']),
                'created_at' => $n->created_at,
            ])
            ->all();

        $profile = $c->customer?->profile_json ?? [];

        $autoState = $this->autoReplyState($c);

        return [
            'conversation' => array_merge(
                $c->only(['id', 'platform', 'thread_id', 'lead_status', 'escalated', 'ai_paused', 'escalation_reason']),
                [
                    'last_inbound_at' => $c->last_inbound_at,
                    'last_outbound_at' => $c->last_outbound_at,
                    'last_read_at' => $c->last_read_at,
                    'unread' => $c->last_inbound_at && (! $c->last_read_at || $c->last_inbound_at > $c->last_read_at),
                    'auto_reply' => $autoState,
                ],
            ),
            'customer' => array_merge($c->customer?->toArray() ?? [], [
                'profile_pic' => $profile['profile_pic'] ?? null,
            ]),
            'assigned' => $c->assignedEmployee?->only(['id', 'name']),
            'messages' => $messages,
            'notes' => $notes,
        ];
    }
}
