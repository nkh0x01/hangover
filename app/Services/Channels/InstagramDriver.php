<?php

namespace App\Services\Channels;

use App\Services\Channels\DTO\InboundEvent;
use App\Services\Channels\DTO\MediaPayload;
use App\Services\Channels\DTO\SendResult;

class InstagramDriver extends AbstractMetaDriver
{
    public function platform(): string
    {
        return 'instagram';
    }

    public function parseInbound(array $payload): array
    {
        $events = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            // Instagram DMs
            foreach (data_get($entry, 'messaging', []) as $m) {
                $senderId = data_get($m, 'sender.id');
                if (! $senderId || $senderId === ($this->config['ig_account_id'] ?? null)) {
                    continue;
                }

                $msg = $m['message'] ?? null;
                if (! $msg || ! empty($msg['is_echo'])) {
                    continue;
                }

                $text = $msg['text'] ?? null;
                $att  = $msg['attachments'][0] ?? null;
                $kind = $att['type'] ?? 'text';
                $media = $att ? ['url' => $att['payload']['url'] ?? null] : [];

                if (! empty($msg['quick_reply']['payload'])) {
                    $kind  = 'interactive';
                    $media = ['payload' => $msg['quick_reply']['payload']];
                }

                $ts = $m['timestamp'] ?? null;
                $receivedAt = $ts !== null ? intdiv((int) $ts, 1000) : time();

                $events[] = new InboundEvent(
                    platform:      'instagram',
                    platformMsgId: (string) ($msg['mid'] ?? ''),
                    threadId:      (string) $senderId,
                    senderId:      (string) $senderId,
                    senderName:    null,
                    kind:          $kind === 'text' ? 'text' : $kind,
                    text:          $text,
                    media:         $media,
                    receivedAt:    $receivedAt,
                    raw:           $m,
                );
            }

            // Instagram comments
            foreach (data_get($entry, 'changes', []) as $change) {
                if (($change['field'] ?? null) !== 'comments') continue;
                $v = $change['value'] ?? [];

                $events[] = new InboundEvent(
                    platform:        'instagram',
                    platformMsgId:   (string) ($v['id'] ?? ''),
                    threadId:        (string) ($v['media']['id'] ?? ''),
                    senderId:        (string) ($v['from']['id'] ?? ''),
                    senderName:      $v['from']['username'] ?? null,
                    kind:            'comment',
                    text:            $v['text'] ?? null,
                    media:           [],
                    receivedAt:      time(),
                    raw:             $v,
                    commentId:       (string) ($v['id'] ?? ''),
                    postId:          (string) ($v['media']['id'] ?? ''),
                    parentCommentId: $v['parent_id'] ?? null,
                );
            }
        }

        return $events;
    }

    public function sendText(string $recipient, string $text): SendResult
    {
        return $this->asSendResult($this->graphPost(
            ($this->config['ig_account_id'] ?? 'me') . '/messages',
            [
                'recipient'      => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message'        => ['text' => $text],
            ],
        ));
    }

    public function sendMedia(string $recipient, MediaPayload $media): SendResult
    {
        return $this->asSendResult($this->graphPost(
            ($this->config['ig_account_id'] ?? 'me') . '/messages',
            [
                'recipient'      => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message'        => [
                    'attachment' => [
                        'type'    => $media->kind,
                        'payload' => ['url' => $media->url],
                    ],
                ],
            ],
        ));
    }

    public function setTyping(string $recipient, bool $on): void
    {
        $this->graphPost(
            ($this->config['ig_account_id'] ?? 'me') . '/messages',
            [
                'recipient'     => ['id' => $recipient],
                'sender_action' => $on ? 'typing_on' : 'typing_off',
            ],
        );
    }

    public function replyToComment(string $commentId, string $text): SendResult
    {
        return $this->asSendResult($this->graphPost(
            $commentId . '/replies',
            ['message' => $text],
        ));
    }

    public function privateReplyToComment(string $commentId, string $text): SendResult
    {
        return $this->asSendResult($this->graphPost(
            ($this->config['ig_account_id'] ?? 'me') . '/messages',
            [
                'recipient'      => ['comment_id' => $commentId],
                'messaging_type' => 'RESPONSE',
                'message'        => ['text' => $text],
            ],
        ));
    }

    public function sendInteractiveButtons(
        string $recipient,
        string $bodyText,
        array $buttons,
        ?MediaPayload $header = null,
        ?string $footerText = null,
    ): SendResult {
        // Instagram supports up to 13 quick replies.
        $quickReplies = array_slice(array_map(fn ($b) => [
            'content_type' => 'text',
            'title'        => mb_substr((string) ($b['title'] ?? ''), 0, 20),
            'payload'      => mb_substr((string) ($b['id'] ?? $b['title'] ?? ''), 0, 1000),
        ], $buttons), 0, 11);

        $message = ['text' => $bodyText];
        if (! empty($quickReplies)) {
            $message['quick_replies'] = $quickReplies;
        }

        return $this->asSendResult($this->graphPost(
            ($this->config['ig_account_id'] ?? 'me') . '/messages',
            [
                'recipient'      => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message'        => $message,
            ],
        ));
    }
}
