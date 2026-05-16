<?php

namespace App\Services\Channels;

use App\Services\Channels\DTO\InboundEvent;
use App\Services\Channels\DTO\MediaPayload;
use App\Services\Channels\DTO\SendResult;

class MessengerDriver extends AbstractMetaDriver
{
    public function platform(): string
    {
        return 'messenger';
    }

    public function parseInbound(array $payload): array
    {
        $events = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            // DM messages
            foreach (data_get($entry, 'messaging', []) as $m) {
                $senderId = data_get($m, 'sender.id');
                if (! $senderId || $senderId === ($this->config['page_id'] ?? null)) {
                    continue; // echoes
                }

                $msg = $m['message'] ?? null;
                if (! $msg) {
                    continue;
                }
                if (! empty($msg['is_echo'])) {
                    continue;
                }

                [$kind, $text, $media] = $this->extractMessage($msg);

                $ts = $m['timestamp'] ?? null;
                $receivedAt = $ts !== null ? intdiv((int) $ts, 1000) : time();

                $events[] = new InboundEvent(
                    platform:      'messenger',
                    platformMsgId: (string) ($msg['mid'] ?? ''),
                    threadId:      (string) $senderId,
                    senderId:      (string) $senderId,
                    senderName:    null,
                    kind:          $kind,
                    text:          $text,
                    media:         $media,
                    receivedAt:    $receivedAt,
                    raw:           $m,
                );
            }

            // Page comments (feed change)
            foreach (data_get($entry, 'changes', []) as $change) {
                if (($change['field'] ?? null) !== 'feed') continue;
                $v = $change['value'] ?? [];
                if (($v['item'] ?? null) !== 'comment' || ($v['verb'] ?? null) !== 'add') continue;

                $events[] = new InboundEvent(
                    platform:        'facebook',
                    platformMsgId:   (string) ($v['comment_id'] ?? ''),
                    threadId:        (string) ($v['post_id'] ?? ''),
                    senderId:        (string) ($v['from']['id'] ?? ''),
                    senderName:      $v['from']['name'] ?? null,
                    kind:            'comment',
                    text:            $v['message'] ?? null,
                    media:           [],
                    receivedAt:      (int) ($v['created_time'] ?? time()),
                    raw:             $v,
                    commentId:       (string) ($v['comment_id'] ?? ''),
                    postId:          (string) ($v['post_id'] ?? ''),
                    parentCommentId: $v['parent_id'] ?? null,
                );
            }
        }

        return $events;
    }

    private function extractMessage(array $msg): array
    {
        $text = $msg['text'] ?? null;
        $att  = $msg['attachments'][0] ?? null;

        if ($att) {
            return match ($att['type'] ?? 'text') {
                'image' => ['image', $text, ['url' => $att['payload']['url'] ?? null]],
                'audio' => ['audio', $text, ['url' => $att['payload']['url'] ?? null]],
                'video' => ['video', $text, ['url' => $att['payload']['url'] ?? null]],
                default => ['text',  $text, []],
            };
        }

        return ['text', $text, []];
    }

    public function sendText(string $recipient, string $text): SendResult
    {
        return $this->asSendResult($this->graphPost(
            'me/messages',
            [
                'recipient'      => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message'        => ['text' => $text],
            ],
            $this->config['page_access_token'] ?? null,
        ));
    }

    public function sendMedia(string $recipient, MediaPayload $media): SendResult
    {
        return $this->asSendResult($this->graphPost(
            'me/messages',
            [
                'recipient'      => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message'        => [
                    'attachment' => [
                        'type'    => $media->kind,
                        'payload' => ['url' => $media->url, 'is_reusable' => true],
                    ],
                ],
            ],
            $this->config['page_access_token'] ?? null,
        ));
    }

    public function setTyping(string $recipient, bool $on): void
    {
        $this->graphPost(
            'me/messages',
            [
                'recipient'      => ['id' => $recipient],
                'sender_action'  => $on ? 'typing_on' : 'typing_off',
            ],
            $this->config['page_access_token'] ?? null,
        );
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
        // Messenger's "Private Reply to comment" is a POST to /me/messages
        // with recipient.comment_id.
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
