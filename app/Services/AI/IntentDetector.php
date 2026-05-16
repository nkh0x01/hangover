<?php

namespace App\Services\AI;

/**
 * Cheap intent classifier — uses Haiku.
 *
 * Returns one of:
 *   greeting | browsing | product_question | price_question |
 *   objection | ready_to_buy | order_status | complaint |
 *   warranty | refund | manager_request | discount_request |
 *   spam | off_topic | unknown
 */
class IntentDetector
{
    public function __construct(private ClaudeClient $claude) {}

    public function detect(string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return 'unknown';
        }

        $system = "You are an intent classifier for a Georgian gadget retailer's chat. " .
            "Output ONLY one label from this set: " .
            "greeting, browsing, product_question, price_question, objection, ready_to_buy, " .
            "order_status, complaint, warranty, refund, manager_request, discount_request, " .
            "spam, off_topic, unknown. No punctuation, no explanation.";

        try {
            $out = $this->claude->complete($system, $text, light: true);
        } catch (\Throwable) {
            return 'unknown';
        }

        $label = strtolower(trim(explode("\n", $out)[0] ?? ''));
        $label = preg_replace('/[^a-z_]/', '', $label) ?: 'unknown';

        $allowed = ['greeting','browsing','product_question','price_question','objection','ready_to_buy','order_status','complaint','warranty','refund','manager_request','discount_request','spam','off_topic','unknown'];
        return in_array($label, $allowed, true) ? $label : 'unknown';
    }

    public function sentiment(string $text): float
    {
        $text = trim((string) $text);
        if ($text === '') {
            return 0.0;
        }

        $system = "You are a sentiment scorer. Read the message and respond ONLY with a single " .
            "number between -1 and 1 — negative for angry/upset, positive for happy, 0 for neutral. " .
            "No words, no explanation.";

        try {
            $out = $this->claude->complete($system, $text, light: true);
        } catch (\Throwable) {
            return 0.0;
        }

        if (preg_match('/-?\d+(\.\d+)?/', $out, $m)) {
            return max(-1.0, min(1.0, (float) $m[0]));
        }
        return 0.0;
    }
}
