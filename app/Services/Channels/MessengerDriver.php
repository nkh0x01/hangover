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
                    platform: 'messenger',
                    platformMsgId: (string) ($msg['mid'] ?? ''),
                    threadId: (string) $senderId,
                    senderId: (string) $senderId,
                    senderName: null,
                    kind: $kind,
                    text: $text,
                    media: $media,
                    receivedAt: $receivedAt,
                    raw: $m,
                );
            }

            // Page comments (feed change)
            foreach (data_get($entry, 'changes', []) as $change) {
                if (($change['field'] ?? null) !== 'feed') {
                    continue;
                }
                $v = $change['value'] ?? [];
                if (($v['item'] ?? null) !== 'comment' || ($v['verb'] ?? null) !== 'add') {
                    continue;
                }

                $events[] = new InboundEvent(
                    platform: 'facebook',
                    platformMsgId: (string) ($v['comment_id'] ?? ''),
                    threadId: (string) ($v['post_id'] ?? ''),
                    senderId: (string) ($v['from']['id'] ?? ''),
                    senderName: $v['from']['name'] ?? null,
                    kind: 'comment',
                    text: $v['message'] ?? null,
                    media: [],
                    receivedAt: (int) ($v['created_time'] ?? time()),
                    raw: $v,
                    commentId: (string) ($v['comment_id'] ?? ''),
                    postId: (string) ($v['post_id'] ?? ''),
                    parentCommentId: $v['parent_id'] ?? null,
                );
            }
        }

        return $events;
    }

    private function extractMessage(array $msg): array
    {
        $text = $msg['text'] ?? null;
        $att = $msg['attachments'][0] ?? null;

        // Quick-reply tap → treat as interactive with payload + visible title.
        if (! empty($msg['quick_reply']['payload'])) {
            return ['interactive', $text, ['payload' => $msg['quick_reply']['payload']]];
        }

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
                'recipient' => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => $text],
            ],
            $this->config['page_access_token'] ?? null,
        ));
    }

    /**
     * Send an image attachment by URL. Messenger Cloud API will fetch the
     * URL and deliver it inline to the user. Returns SendResult — same
     * shape as sendText().
     */
    public function sendImage(string $recipient, string $imageUrl, ?string $captionText = null): SendResult
    {
        $resp = $this->graphPost(
            'me/messages',
            [
                'recipient' => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message' => [
                    'attachment' => [
                        'type' => 'image',
                        'payload' => ['url' => $imageUrl, 'is_reusable' => true],
                    ],
                ],
            ],
            $this->config['page_access_token'] ?? null,
        );

        // Optionally append a caption as a separate text message
        if ($captionText !== null && $captionText !== '') {
            $this->graphPost(
                'me/messages',
                [
                    'recipient' => ['id' => $recipient],
                    'messaging_type' => 'RESPONSE',
                    'message' => ['text' => $captionText],
                ],
                $this->config['page_access_token'] ?? null,
            );
        }

        return $this->asSendResult($resp);
    }

    /**
     * Fetch public profile (name + photo) for a PSID. Returns
     * ['ok' => bool, 'name' => ?string, 'profile_pic' => ?string, 'error' => ?string].
     * Requires pages_show_list + pages_messaging or an admin/tester role.
     * Always fails gracefully — never throws.
     */
    public function fetchProfile(string $psid): array
    {
        $resp = $this->graphGet($psid, [
            'fields' => 'name,profile_pic',
        ], $this->config['page_access_token'] ?? null);

        $status = $resp['status'] ?? 0;
        $data = $resp['data'] ?? [];

        if ($status >= 200 && $status < 300) {
            return [
                'ok' => true,
                'name' => $data['name'] ?? null,
                'profile_pic' => $data['profile_pic'] ?? null,
                'error' => null,
            ];
        }

        return [
            'ok' => false,
            'name' => null,
            'profile_pic' => null,
            'error' => $data['error']['message'] ?? "http_{$status}",
        ];
    }

    public function sendMedia(string $recipient, MediaPayload $media): SendResult
    {
        return $this->asSendResult($this->graphPost(
            'me/messages',
            [
                'recipient' => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message' => [
                    'attachment' => [
                        'type' => $media->kind,
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
                'recipient' => ['id' => $recipient],
                'sender_action' => $on ? 'typing_on' : 'typing_off',
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
                'recipient' => ['comment_id' => $commentId],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => $text],
            ],
            $this->config['page_access_token'] ?? null,
        ));
    }

    public function sendInteractiveButtons(
        string $recipient,
        string $bodyText,
        array $buttons,
        ?MediaPayload $header = null,
        ?string $footerText = null,
    ): SendResult {
        // Messenger supports up to 13 quick replies and 3 button-template buttons.
        // We use quick_replies for short flows (≤ 11 chars/title), and the
        // button template for richer cases.
        $quickReplies = array_slice(array_map(fn ($b) => [
            'content_type' => 'text',
            'title' => mb_substr((string) ($b['title'] ?? ''), 0, 20),
            'payload' => mb_substr((string) ($b['id'] ?? $b['title'] ?? ''), 0, 1000),
        ], $buttons), 0, 11);

        $message = ['text' => $bodyText];
        if (! empty($quickReplies)) {
            $message['quick_replies'] = $quickReplies;
        }

        return $this->asSendResult($this->graphPost(
            'me/messages',
            [
                'recipient' => ['id' => $recipient],
                'messaging_type' => 'RESPONSE',
                'message' => $message,
            ],
            $this->config['page_access_token'] ?? null,
        ));
    }
}
