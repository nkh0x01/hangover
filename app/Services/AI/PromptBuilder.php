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
        $stable .= "\n\n" . $this->branchBlock();
        $stable .= "\n\n" . $this->salesPlaybook();

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

    /**
     * Store-branch grounding block: the ONLY source the bot may use for
     * location / address / working-hours answers. Prevents hallucinated
     * addresses — anything not covered here must be escalated.
     */
    private function branchBlock(): string
    {
        $branches = (array) config('branches.list', []);

        if ($branches === []) {
            return 'Store branches: not configured. If a customer asks about a branch, address, city or '
                . 'working hours, do NOT invent anything — call escalate_to_human or say a colleague will confirm.';
        }

        $lines = [];
        foreach ($branches as $b) {
            $lines[] = '- ' . ($b['name'] ?? '?') . ': ' . ($b['address'] ?? '') . ' — ' . ($b['hours'] ?? '');
        }
        $phone = config('branches.phone');

        return "Store branches — the ONLY source for location / address / working-hours facts:\n"
            . implode("\n", $lines)
            . ($phone ? "\nPhone (all branches): {$phone}" : '')
            . "\n\nLocation rule: answer where-are-you / address / working-hours questions ONLY from this list. "
            . 'NEVER invent or guess an address, city or hours. If a customer asks about a location not listed, '
            . 'or you are unsure, call escalate_to_human. Do not send website links for this.';
    }

    /**
     * The sales playbook: qualify → anchor up → cross-sell. Baked into the
     * system prompt so the tool-use engine follows a consistent, margin-aware
     * flow instead of dumping the cheapest match.
     */
    private function salesPlaybook(): string
    {
        return <<<TXT
Sales playbook — follow this order on every buying conversation:

1) QUALIFY BEFORE RECOMMENDING. On a vague product request, do NOT search or show a product yet. First ask 1-2 short questions to learn (a) the exact device/context (e.g. WHICH iPhone model) and (b) the budget / price range they have in mind. Only after you know both, search and recommend.

2) ANCHOR UP. When you recommend, offer TWO options: one around the MIDDLE of their stated budget, and one about 30% (or a little more) ABOVE it. Frame the pricier one honestly and briefly, e.g.: "თქვენი ბიუჯეტი ეს არის, მაგრამ მე ამას გირჩევდით — <1 short reason>." Never hide or fake a price. Goal: guide each customer to the best value they will accept.

3) CROSS-SELL. Once the customer settles on a product, proactively offer ONE complementary accessory that genuinely fits it (case / screen protector / charger / cable / etc.) — find it with search_products, never invent it. Frame it as an online-order perk, e.g.: "რადგან ონლაინ იღებთ, თუ ამასაც დაამატებთ, ამ აქსესუარზე პატარა ფასდაკლებას გაგიკეთებთ." Offer the discount only as a small courtesy on the ADD-ON; do NOT state a specific % (the checkout applies the real amount). If there is no genuine matching accessory in the catalog, skip step 3.

Every product, price and stock fact must still come from the tools — never invent one.
TXT;
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
