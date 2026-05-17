<?php

namespace App\Services\Comments;

use App\Models\Comment;
use App\Services\AI\ClaudeClient;
use App\Services\AI\IntentDetector;
use App\Services\Channels\ChannelManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles incoming FB/IG comments. Policy is conservative: never
 * argue, never delete, always polite, always nudge to DM.
 */
class CommentResponder
{
    public function __construct(
        private ChannelManager $channels,
        private IntentDetector $intent,
        private ClaudeClient $claude,
    ) {}

    public function handle(Comment $comment): void
    {
        $text = trim((string) $comment->body);
        if ($text === '') {
            return;
        }

        $intent = $this->intent->detect($text);
        $sentiment = $this->intent->sentiment($text);

        $comment->update([
            'intent' => $intent,
            'sentiment' => $sentiment,
        ]);

        // Escalate (don't reply publicly) on risky comments.
        if ($sentiment < -0.4 || in_array($intent, ['complaint', 'refund', 'warranty', 'manager_request'], true)) {
            $comment->update(['escalated' => true]);

            return;
        }

        if (in_array($intent, ['spam', 'off_topic'], true)) {
            $comment->update(['replied' => false]);

            return;
        }

        $reply = $this->craftPublicReply($text, $intent);

        $driver = $this->channels->driver($comment->platform);

        try {
            $result = $driver->replyToComment($comment->comment_id, $reply);
            if (! $result->ok) {
                Log::warning('comment.public_reply.failed', ['comment' => $comment->comment_id, 'err' => $result->error]);

                return;
            }
            $comment->update([
                'replied' => true,
                'reply_body' => $reply,
                'reply_comment_id' => $result->platformMsgId,
            ]);

            // Try to pull them into DM via Private Reply.
            $dm = $this->craftPrivateNudge($text, $intent);
            $pr = $driver->privateReplyToComment($comment->comment_id, $dm);
            $comment->update(['private_reply_attempted' => true]);
            if (! $pr->ok) {
                Log::info('comment.private_reply.failed', ['err' => $pr->error]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function craftPublicReply(string $text, string $intent): string
    {
        // Cheap canned replies — short, on-brand. Random pick keeps it human.
        $bank = match ($intent) {
            'price_question' => [
                'მოგწერთ პირადში ❤️',
                'დეტალებს პირადში გამოგიგზავნით.',
            ],
            'product_question' => [
                'მოგწერთ პირადში ❤️',
                'პირადში მოგწერეთ ყველაფერი 🤍',
                'ეს მოდელი გვაქვს ❤️ პირადში დაგიკონკრეტებთ.',
            ],
            'ready_to_buy' => [
                'პირადში მოგწერეთ, შევუკვეთოთ! 🛍️',
                'ფანტასტიკურია — დეტალები პირადში ❤️',
            ],
            default => [
                'მოგწერთ პირადში ❤️',
                'პირადში მოგწერეთ.',
            ],
        };

        return $bank[array_rand($bank)];
    }

    private function craftPrivateNudge(string $text, string $intent): string
    {
        return 'გამარჯობა! 👋 თქვენი კომენტარის გამო მოგწერთ პირადში. ' .
            'მითხარით კონკრეტული მოდელი/კითხვა და ყველაფერს ერთად მოვაგვაროთ 🤍';
    }
}
