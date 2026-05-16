<?php

namespace App\Services\Channels;

use App\Services\Channels\DTO\InboundEvent;
use App\Services\Channels\DTO\MediaPayload;
use App\Services\Channels\DTO\SendResult;

/**
 * Lightweight driver dedicated to Facebook page comments. The Messenger
 * driver already handles incoming "feed" events from FB, so this class
 * mostly exists so the comment responder can be dispatched against a
 * "facebook" platform string without coupling to MessengerDriver
 * internals.
 */
class FacebookCommentsDriver extends AbstractMetaDriver
{
    public function platform(): string
    {
        return 'facebook';
    }

    public function parseInbound(array $payload): array
    {
        // Handled by MessengerDriver; this driver is outbound-only.
        return [];
    }

    public function sendText(string $recipient, string $text): SendResult
    {
        // Not applicable for Facebook page comments.
        return SendResult::fail('not_supported');
    }

    public function sendMedia(string $recipient, MediaPayload $media): SendResult
    {
        return SendResult::fail('not_supported');
    }

    public function replyToComment(string $commentId, string $text): SendResult
    {
        return $this->asSendResult($this->graphPost(
            $commentId . '/comments',
            ['message' => $text],
            $this->config['page_access_token'] ?? null,
        ));
    }

    public function privateReplyToComment(string $commentId, string $text): SendResult
    {
        return $this->asSendResult($this->graphPost(
            'me/messages',
            [
                'recipient'      => ['comment_id' => $commentId],
                'messaging_type' => 'RESPONSE',
                'message'        => ['text' => $text],
            ],
            $this->config['page_access_token'] ?? null,
        ));
    }
}
