<?php

namespace App\Services\Escalation;

use App\Models\Conversation;
use App\Services\AI\IntentDetector;

/**
 * Pre-AI filter. Runs before the expensive Claude call so we can
 * short-circuit obvious escalation cases.
 */
class EscalationDetector
{
    public function __construct(private IntentDetector $intent) {}

    public function shouldEscalate(string $text, Conversation $conversation): ?array
    {
        // 1. Phrase triggers (cheap substring match).
        foreach ((array) config('escalation.phrase_triggers', []) as $needle) {
            if (mb_stripos($text, $needle) !== false) {
                return ['reason' => 'phrase:' . mb_substr($needle, 0, 24), 'urgency' => 'medium'];
            }
        }

        // 2. Customer flagged VIP & expressed dissatisfaction?
        if ($conversation->customer?->is_vip && $this->intent->sentiment($text) < -0.2) {
            return ['reason' => 'vip_unhappy', 'urgency' => 'high'];
        }

        return null;
    }
}
