<?php

namespace App\Services\Channels\DTO;

/**
 * Normalized inbound event. Every channel driver translates platform
 * payloads into this shape before anything else touches them.
 */
final class InboundEvent
{
    public function __construct(
        public readonly string  $platform,
        public readonly string  $platformMsgId,
        public readonly string  $threadId,
        public readonly string  $senderId,
        public readonly ?string $senderName,
        public readonly string  $kind,        // text|image|audio|video|interactive|comment
        public readonly ?string $text,
        public readonly array   $media,       // ['url' => ..., 'mime' => ...]
        public readonly int     $receivedAt,
        public readonly array   $raw,
        public readonly ?string $commentId    = null,
        public readonly ?string $postId       = null,
        public readonly ?string $parentCommentId = null,
    ) {}

    public function isComment(): bool
    {
        return $this->kind === 'comment';
    }

    public function toArray(): array
    {
        return [
            'platform'         => $this->platform,
            'platform_msg_id'  => $this->platformMsgId,
            'thread_id'        => $this->threadId,
            'sender_id'        => $this->senderId,
            'sender_name'      => $this->senderName,
            'kind'             => $this->kind,
            'text'             => $this->text,
            'media'            => $this->media,
            'received_at'      => $this->receivedAt,
            'comment_id'       => $this->commentId,
            'post_id'          => $this->postId,
            'parent_comment_id'=> $this->parentCommentId,
            'raw'              => $this->raw,
        ];
    }
}
