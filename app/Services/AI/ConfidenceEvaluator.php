<?php

namespace App\Services\AI;

/**
 * Parses the <meta>...</meta> tail Claude appends to every reply and
 * applies the confidence floor.
 */
class ConfidenceEvaluator
{
    private const TAG_RE = '/<meta>\s*({.*?})\s*<\/meta>/s';

    public function parse(string $reply): array
    {
        $meta = ['confidence' => null, 'intent' => null, 'next_action' => null];

        if (preg_match(self::TAG_RE, $reply, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                $meta = array_merge($meta, $decoded);
            }
        }

        return [
            'meta' => $meta,
            'clean' => trim(preg_replace(self::TAG_RE, '', $reply)),
        ];
    }

    public function passesFloor(?float $confidence): bool
    {
        if ($confidence === null) {
            return true; // missing metadata — let it through but log
        }

        return $confidence >= (float) config('chatbot.ai.min_confidence', 0.62);
    }
}
