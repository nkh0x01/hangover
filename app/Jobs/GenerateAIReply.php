<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Services\AI\ReplyEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs after the debounce window. Will abort if a newer inbound has
 * already rotated the conversation's pending_reply_job_id — that's how
 * we cancel stale replies cheaply.
 */
class GenerateAIReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(public int $conversationId, public string $jobToken) {}

    public function handle(ReplyEngine $engine): void
    {
        $conversation = Conversation::with('customer')->find($this->conversationId);
        if (! $conversation) {
            return;
        }

        // Stale check: a newer inbound rotated the token.
        if ($conversation->pending_reply_job_id !== $this->jobToken) {
            return;
        }

        if (! $conversation->isAIEnabled()) {
            return;
        }

        try {
            $engine->reply($conversation);

            AuditLog::record('ai', 'reply.sent', 'conversation', $conversation->id, [
                'token' => $this->jobToken,
            ]);
        } catch (Throwable $e) {
            report($e);
            AuditLog::record('ai', 'reply.failed', 'conversation', $conversation->id, [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(?Throwable $e = null): void
    {
        AuditLog::record('ai', 'reply.dead_letter', 'conversation', $this->conversationId, [
            'error' => $e?->getMessage(),
        ]);
    }
}
