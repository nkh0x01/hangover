<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\Message;

class UnifiedInbox
{
    public function list(array $filters = [], int $limit = 50): array
    {
        $q = Conversation::query()
            ->with(['customer'])
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
        if (! empty($filters['q'])) {
            $needle = $filters['q'];
            $q->where(function ($qq) use ($needle) {
                $qq->whereHas('customer', fn ($c) =>
                    $c->where('display_name', 'like', "%$needle%")
                      ->orWhere('phone', 'like', "%$needle%")
                      ->orWhere('platform_user_id', 'like', "%$needle%"));
            });
        }

        $rows = $q->limit($limit)->get();

        return $rows->map(function (Conversation $c) {
            $last = Message::where('conversation_id', $c->id)->latest('id')->first();
            return [
                'id'             => $c->id,
                'platform'       => $c->platform,
                'thread_id'      => $c->thread_id,
                'lead_status'    => $c->lead_status,
                'escalated'      => $c->escalated,
                'ai_paused'      => $c->ai_paused,
                'last_inbound'   => $c->last_inbound_at,
                'last_outbound'  => $c->last_outbound_at,
                'last_message'   => $last ? [
                    'direction' => $last->direction,
                    'body'      => mb_substr((string) $last->body, 0, 200),
                    'is_ai'     => (bool) $last->is_ai,
                    'created_at'=> $last->created_at,
                ] : null,
                'customer' => [
                    'id'      => $c->customer?->id,
                    'name'    => $c->customer?->display_name,
                    'handle'  => $c->customer?->platform_user_id,
                    'is_vip'  => (bool) $c->customer?->is_vip,
                    'memory'  => $c->customer?->profile_json ?? [],
                ],
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
                'id'         => $m->id,
                'direction'  => $m->direction,
                'kind'       => $m->kind,
                'body'       => $m->body,
                'media'      => $m->media_json,
                'is_ai'      => (bool) $m->is_ai,
                'confidence' => $m->confidence,
                'intent'     => $m->intent,
                'author'     => $m->author?->only(['id', 'name']),
                'created_at' => $m->created_at,
            ])
            ->all();

        return [
            'conversation' => $c->only(['id','platform','thread_id','lead_status','escalated','ai_paused','escalation_reason']),
            'customer'     => $c->customer?->toArray(),
            'assigned'     => $c->assignedEmployee?->only(['id', 'name']),
            'messages'     => $messages,
        ];
    }
}
