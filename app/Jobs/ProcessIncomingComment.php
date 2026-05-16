<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Services\Comments\CommentResponder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 60;

    public function __construct(public array $event) {}

    public function handle(CommentResponder $responder): void
    {
        $comment = Comment::firstOrCreate(
            ['comment_id' => $this->event['comment_id']],
            [
                'platform' => $this->event['platform'],
                'post_id' => $this->event['post_id'] ?? '',
                'parent_comment_id' => $this->event['parent_comment_id'] ?? null,
                'platform_user_id' => $this->event['sender_id'] ?? null,
                'author_name' => $this->event['sender_name'] ?? null,
                'body' => $this->event['text'] ?? null,
                'posted_at' => isset($this->event['received_at']) ? now()->setTimestamp((int) $this->event['received_at']) : now(),
            ],
        );

        // Don't reply to ourselves.
        $ourPageIds = array_filter([
            config('channels.facebook.page_id'),
            config('channels.messenger.page_id'),
            config('channels.instagram.ig_account_id'),
        ]);
        if (in_array($comment->platform_user_id, $ourPageIds, true)) {
            return;
        }

        if ($comment->replied || $comment->escalated) {
            return;
        }

        $responder->handle($comment->fresh());
    }
}
