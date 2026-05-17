<?php

namespace App\Services\AI;

use App\Models\AiPrompt;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;

/**
 * Assembles the system prompt + conversation history for Claude.
 *
 * Cacheable parts (brand voice + system prompt) are returned as the
 * first system block with cache_control so prompt caching kicks in.
 */
class PromptBuilder
{
    public function systemBlocks(Customer $customer, Conversation $conversation, string $tone): array
    {
        $brand = config('ai.brand_voice');
        $stored = AiPrompt::active('system');

        $stable = $stored?->body ?? $this->defaultSystem($brand);

        $voiceList = "Voice:\n- " . implode("\n- ", $brand['voice'] ?? []);
        $forbidden = "Forbidden:\n- " . implode("\n- ", $brand['forbidden'] ?? []);

        $stableBlock = [
            'type' => 'text',
            'text' => "$stable\n\n$voiceList\n\n$forbidden",
            'cache_control' => ['type' => 'ephemeral'],
        ];

        $tonePresets = config('chatbot.tones');
        $tonePreset = $tonePresets[$tone] ?? $tonePresets['friendly_warm'];

        $memory = $customer->profile_json ?? [];
        $memoryText = empty($memory)
            ? '(no prior memory)'
            : json_encode($memory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $dynamicBlock = [
            'type' => 'text',
            'text' => "Customer memory snapshot:\n$memoryText\n\n" .
                "Detected tone preset: $tone — " . ($tonePreset['description'] ?? '') . "\n" .
                'Aim for ≤ ' . ($tonePreset['max_words'] ?? 60) . ' words per reply. ' .
                'Emoji density target: ' . ($tonePreset['emoji_rate'] ?? 0.2) . ".\n\n" .
                "Output protocol:\n" .
                "1) Reply only in Georgian unless the customer wrote in another language.\n" .
                '2) Use tools to look up any factual claim about stock, price, ' .
                "   discounts, delivery, warranty. Never invent these.\n" .
                '3) End every reply with a JSON tail wrapped in <meta>…</meta> on the ' .
                '   last line containing: {"confidence": 0..1, "intent": "...", ' .
                "   \"next_action\": \"reply|ask|recommend|escalate|collect_order\"}.\n" .
                '4) If confidence < ' . config('chatbot.ai.min_confidence') . ' or you ' .
                '   cannot answer truthfully, call the escalate_to_human tool instead ' .
                "   of replying.\n" .
                '5) Keep the customer in chat. Do not paste website links unless ' .
                '   absolutely necessary (e.g. a generated payment link).',
        ];

        return [$stableBlock, $dynamicBlock];
    }

    /**
     * Build messages[] from conversation history.
     *
     * @return array<int, array{role: string, content: array|string}>
     */
    public function historyMessages(Conversation $conversation, int $turns = 12): array
    {
        $rows = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit($turns * 2)
            ->get()
            ->reverse()
            ->values();

        $out = [];
        foreach ($rows as $m) {
            if (! $m->body) {
                continue;
            }
            $out[] = [
                'role' => $m->direction === Message::DIRECTION_IN ? 'user' : 'assistant',
                'content' => $m->body,
            ];
        }

        return $out;
    }

    private function defaultSystem(array $brand): string
    {
        $name = $brand['company_name'] ?? 'Gadget';

        return "You are the digital sales consultant for $name, a gadget and accessories retailer in Georgia. " .
            'You answer customers on WhatsApp, Facebook Messenger and Instagram Direct. ' .
            'You behave like a real human Gadget employee: friendly, brief, helpful. ' .
            'Your job is to greet, understand the need, recommend real products that are in stock, ' .
            'handle objections, collect order details and either create an order draft or invite the ' .
            'customer to a branch. You are NOT allowed to invent prices, stock, discounts or warranty ' .
            'terms — those facts come exclusively from the tools you can call. If a tool fails or you ' .
            'are unsure, hand the conversation to a human via escalate_to_human.';
    }
}
