<?php

namespace App\Services\Channels;

use App\Services\Channels\DTO\InboundEvent;
use App\Services\Channels\DTO\MediaPayload;
use App\Services\Channels\DTO\SendResult;

class WhatsAppDriver extends AbstractMetaDriver
{
    public function platform(): string
    {
        return 'whatsapp';
    }

    public function parseInbound(array $payload): array
    {
        $events = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = $change['value'] ?? [];

                $contacts = collect($value['contacts'] ?? [])
                    ->keyBy(fn ($c) => $c['wa_id'] ?? '')
                    ->all();

                foreach (($value['messages'] ?? []) as $m) {
                    $from = $m['from'] ?? '';
                    $name = $contacts[$from]['profile']['name'] ?? null;

                    [$kind, $text, $media] = $this->extractContent($m);

                    $events[] = new InboundEvent(
                        platform:      'whatsapp',
                        platformMsgId: (string) ($m['id'] ?? ''),
                        threadId:      (string) $from,
                        senderId:      (string) $from,
                        senderName:    $name,
                        kind:          $kind,
                        text:          $text,
                        media:         $media,
                        receivedAt:    (int) ($m['timestamp'] ?? time()),
                        raw:           $m,
                    );
                }
            }
        }

        return $events;
    }

    private function extractContent(array $m): array
    {
        $type = $m['type'] ?? 'text';

        return match ($type) {
            'text'        => ['text', $m['text']['body'] ?? null, []],
            'image'       => ['image', $m['image']['caption'] ?? null, ['id' => $m['image']['id'] ?? null, 'mime' => $m['image']['mime_type'] ?? null]],
            'audio'       => ['audio', null, ['id' => $m['audio']['id'] ?? null, 'mime' => $m['audio']['mime_type'] ?? null]],
            'video'       => ['video', $m['video']['caption'] ?? null, ['id' => $m['video']['id'] ?? null]],
            'document'    => ['document', $m['document']['caption'] ?? null, ['id' => $m['document']['id'] ?? null]],
            'interactive' => ['interactive', $this->interactiveText($m['interactive'] ?? []), []],
            'button'      => ['interactive', $m['button']['text'] ?? null, []],
            default       => [$type, null, []],
        };
    }

    private function interactiveText(array $i): ?string
    {
        return $i['button_reply']['title']
            ?? $i['list_reply']['title']
            ?? null;
    }

    public function sendText(string $recipient, string $text): SendResult
    {
        $resp = $this->graphPost(
            $this->config['phone_number_id'] . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'to'                => $recipient,
                'type'              => 'text',
                'text'              => ['preview_url' => false, 'body' => $text],
            ],
        );

        return $this->asSendResult($resp);
    }

    public function sendMedia(string $recipient, MediaPayload $media): SendResult
    {
        $resp = $this->graphPost(
            $this->config['phone_number_id'] . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'to'                => $recipient,
                'type'              => $media->kind,
                $media->kind        => array_filter([
                    'link'    => $media->url,
                    'caption' => $media->caption,
                ]),
            ],
        );

        return $this->asSendResult($resp);
    }

    public function setTyping(string $recipient, bool $on): void
    {
        // WhatsApp Cloud API supports a "typing" status via /messages with
        // type=reaction or via the new "status" endpoint depending on the
        // API version. Implementations vary — left as a soft no-op rather
        // than risk a wrong call. The pacing in ReplyEngine already gives
        // a natural delay.
    }
}
