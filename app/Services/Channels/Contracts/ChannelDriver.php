<?php

namespace App\Services\Channels\Contracts;

use App\Services\Channels\DTO\InboundEvent;
use App\Services\Channels\DTO\MediaPayload;
use App\Services\Channels\DTO\SendResult;
use Illuminate\Http\Request;

interface ChannelDriver
{
    /** Platform identifier ("whatsapp", "messenger", "instagram", "facebook"). */
    public function platform(): string;

    /**
     * Handle the verification handshake Meta sends on webhook setup.
     * Return the challenge string on success or null on failure.
     */
    public function verifyWebhook(Request $r): ?string;

    /**
     * Verify the X-Hub-Signature-256 header on inbound POSTs.
     * Throws \App\Exceptions\WebhookVerificationException on failure.
     */
    public function verifySignature(Request $r): void;

    /**
     * Translate raw platform payload into normalized InboundEvent objects.
     * One webhook delivery may contain multiple events.
     *
     * @return InboundEvent[]
     */
    public function parseInbound(array $payload): array;

    public function sendText(string $recipient, string $text): SendResult;

    public function sendMedia(string $recipient, MediaPayload $media): SendResult;

    /** Best-effort typing indicator. No-op if unsupported. */
    public function setTyping(string $recipient, bool $on): void;

    /** Reply to a public comment (FB/IG). Throws if unsupported. */
    public function replyToComment(string $commentId, string $text): SendResult;

    /**
     * Send a Private Reply (Messenger feature: respond to a comment as
     * a DM). No-op if unsupported.
     */
    public function privateReplyToComment(string $commentId, string $text): SendResult;
}
