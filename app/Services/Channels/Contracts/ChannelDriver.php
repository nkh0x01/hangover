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

    /**
     * Send a rich interactive message with quick-reply buttons.
     *
     * @param string $recipient
     * @param string $bodyText           main message body
     * @param array<int, array{id: string, title: string}> $buttons up to 3 buttons (WhatsApp limit)
     * @param ?MediaPayload $header      optional header (image/video/document)
     * @param ?string $footerText        optional footer text
     */
    public function sendInteractiveButtons(
        string $recipient,
        string $bodyText,
        array $buttons,
        ?MediaPayload $header = null,
        ?string $footerText = null,
    ): SendResult;

    /**
     * Send a pre-approved WhatsApp message template.
     *
     * @param string $recipient
     * @param string $templateName       must match a template approved on the WhatsApp Business platform
     * @param string $languageCode       e.g. "ka", "en"
     * @param array  $components         components array per WhatsApp spec
     */
    public function sendTemplate(
        string $recipient,
        string $templateName,
        string $languageCode,
        array $components = [],
    ): SendResult;

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
