<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Gadget\ProductSearchService;
use App\Services\SettingsService;
use RuntimeException;
use Throwable;

/**
 * Generates Georgian reply suggestions for the admin inbox WITHOUT sending.
 *
 *   STRICT ARCHITECTURE
 *   -------------------
 *   The AI is never allowed to invent products, prices, stock, discounts
 *   or delivery promises. Every product mention must originate from a
 *   WooCommerce search result. Flow:
 *
 *     1. Intent detection (Claude/Haiku, 1-shot)
 *     2a. If "product_query" → extract keywords → WC search →
 *         pass real products as JSON to Claude → Claude composes Georgian
 *         text + cites product_ids → VALIDATOR checks all cited ids are in
 *         the provided list AND that no rogue brand/model leaked into the
 *         text. If anything fails → return the safe fallback line.
 *     2b. Otherwise → general non-product reply, system prompt forbids
 *         specific product names/prices/stock. If reply still leaks a
 *         brand name → return the safe fallback line.
 *
 *   The "safe fallback" is exactly:
 *     ამაზე ზუსტად გადავამოწმებ გუნდთან და მოგწერთ.
 */
class AiSuggestionService
{
    private const FALLBACK = 'ამაზე ზუსტად გადავამოწმებ გუნდთან და მოგწერთ.';

    /**
     * Brands and model markers commonly mentioned in chats. Used by the
     * leak detector — any of these appearing in a "general" Claude reply
     * (when we did not pass a product list) means Claude went off-script.
     */
    private const BRAND_TOKENS = [
        'iphone', 'samsung', 'galaxy', 'xiaomi', 'redmi', 'huawei', 'honor',
        'oppo', 'vivo', 'realme', 'pixel', 'oneplus', 'airpods', 'macbook',
        'ipad', 'apple watch', 'sony', 'jbl', 'bose', 'anker', 'lenovo',
        'asus', 'acer', 'dell', 'hp ', 'msi', 'dyson', 'philips', 'gopro',
        'dji', 'logitech', 'razer', 'hyperx', 'baseus', 'hoco', 'remax',
        'ugreen', 'beats', 'powerbeats', 'shokz',
    ];

    public function __construct(
        private ClaudeClient $claude,
        private SettingsService $settings,
        private ProductSearchService $products,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   suggestion: ?string,
     *   error: ?string,
     *   model: ?string,
     *   source: string,              // 'wc_grounded' | 'no_products_fallback' | 'general' | 'validator_rejected' | 'extract_failed'
     *   intent: ?string,
     *   query: ?string,
     *   products: array,             // products considered (may be empty)
     * }
     */
    public function suggest(Conversation $conversation): array
    {
        // Pre-flight: key must exist
        $key = $this->settings->get('ANTHROPIC_API_KEY');
        if (! $key) {
            return $this->blank(error: 'ANTHROPIC_API_KEY არ არის — გადადი /admin/integrations → AI');
        }

        $conversation->loadMissing('customer');
        $history = $this->lastInboundMessages($conversation, 5);
        if ($history->isEmpty()) {
            return $this->blank(error: 'კლიენტისგან მესიჯი ჯერ არ მოვიდა');
        }

        // Step 1: intent detection + (if product) query extraction
        try {
            $analyzed = $this->analyzeIntent($history);
        } catch (Throwable $e) {
            return $this->blank(error: $this->categorizeError($e), source: 'extract_failed');
        }

        $intent = $analyzed['intent'];
        $query = $analyzed['query'] ?? null;

        if ($intent === 'product_query' && $query) {
            $search = $this->products->search($query, [], 5);
            $items = $search['items'] ?? [];
            $wcStatus = $search['status'] ?? 'error';

            // WC unavailable (auth_failed / blocked / network error) — DIFFERENT
            // from "no products". Surface a clearer source label so the auto-
            // reply gate can log "woocommerce_unavailable" instead of treating
            // it as an honest empty result.
            if (in_array($wcStatus, ['auth_failed', 'blocked', 'error'], true)) {
                return [
                    'ok' => true,
                    'suggestion' => self::FALLBACK,
                    'error' => 'wc_'.$wcStatus.': '.($search['error'] ?? '?'),
                    'model' => null,
                    'source' => 'wc_unavailable',
                    'intent' => $intent,
                    'query' => $query,
                    'products' => [],
                ];
            }

            if (! $search['ok'] || empty($items)) {
                // No products found in WC — hardcoded fallback (do NOT let Claude invent)
                return [
                    'ok' => true,
                    'suggestion' => self::FALLBACK,
                    'error' => null,
                    'model' => null,
                    'source' => 'no_products_fallback',
                    'intent' => $intent,
                    'query' => $query,
                    'products' => [],
                    'queries_tried' => $search['queries_tried'] ?? [],
                    'matched_query' => $search['matched_query'] ?? null,
                ];
            }

            // Real WC products → Claude composes grounded reply (with memory)
            $memory = $conversation->customer?->profile_json ?? null;
            try {
                $composed = $this->composeProductReply($history->last(), $items, $memory);
            } catch (Throwable $e) {
                return $this->blank(error: $this->categorizeError($e), source: 'extract_failed');
            }

            // Soft upsell: append the upsell_hint inline (NOT a separate message).
            // Claude is instructed to only mention an accessory CATEGORY, no
            // specific product — so it can't invent product/price/stock.
            if (! empty($composed['upsell_hint']) && mb_strlen($composed['upsell_hint']) < 200) {
                $hint = trim($composed['upsell_hint']);
                // Avoid duplicate-ish if Claude already worked it into the body
                if (! empty($hint) && ! str_contains(mb_strtolower($composed['reply']), mb_strtolower(mb_substr($hint, 0, 15)))) {
                    $composed['reply'] = rtrim($composed['reply'])."\n\n".$hint;
                }
            }

            // Validator: ensure cited products are in provided list AND no
            // other brand/model leaked
            if (! $this->validateProductReply($composed['reply'], $composed['product_ids'], $items)) {
                return [
                    'ok' => true,
                    'suggestion' => self::FALLBACK,
                    'error' => null,
                    'model' => 'haiku',
                    'source' => 'validator_rejected',
                    'intent' => $intent,
                    'query' => $query,
                    'products' => $items,
                ];
            }

            return [
                'ok' => true,
                'suggestion' => $composed['reply'],
                'error' => null,
                'model' => 'haiku',
                'source' => 'wc_grounded',
                'intent' => $intent,
                'query' => $query,
                'products' => $items,
            ];
        }

        // Non-product intent: general reply, but FORBID product mentions
        $memory = $conversation->customer?->profile_json ?? null;
        try {
            $generalText = $this->composeGeneralReply($conversation, $history, $memory);
        } catch (Throwable $e) {
            return $this->blank(error: $this->categorizeError($e));
        }

        // If general reply leaked a brand/model, override with fallback
        if ($this->mentionsAnyBrand($generalText)) {
            return [
                'ok' => true,
                'suggestion' => self::FALLBACK,
                'error' => null,
                'model' => 'haiku',
                'source' => 'validator_rejected',
                'intent' => $intent,
                'query' => null,
                'products' => [],
            ];
        }

        return [
            'ok' => true,
            'suggestion' => $generalText,
            'error' => null,
            'model' => 'haiku',
            'source' => 'general',
            'intent' => $intent,
            'query' => null,
            'products' => [],
        ];
    }

    /**
     * Explicit product recommendation entry-point (Products panel button).
     * Same strict architecture, just always biased towards searching first.
     *
     * @return array{
     *   ok: bool, error: ?string,
     *   query: ?string, products: array,
     *   draft_text: ?string, source: string,
     * }
     */
    public function recommendProducts(Conversation $conversation): array
    {
        $key = $this->settings->get('ANTHROPIC_API_KEY');
        if (! $key) {
            return ['ok' => false, 'error' => 'ANTHROPIC_API_KEY არ არის — გადადი /admin/integrations → AI', 'query' => null, 'products' => [], 'draft_text' => null, 'source' => 'no_key'];
        }

        $history = $this->lastInboundMessages($conversation, 5);
        if ($history->isEmpty()) {
            return ['ok' => false, 'error' => 'კლიენტისგან მესიჯი ჯერ არ მოვიდა', 'query' => null, 'products' => [], 'draft_text' => null, 'source' => 'no_history'];
        }

        try {
            $analyzed = $this->analyzeIntent($history);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $this->categorizeError($e), 'query' => null, 'products' => [], 'draft_text' => null, 'source' => 'extract_failed'];
        }
        $query = $analyzed['query'] ?? null;

        if (! $query) {
            // Force a generic search from raw last message if intent doesn't
            // give us a search query
            $query = mb_strtolower(trim((string) $history->last()));
            $query = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $query);
            $query = trim($query);
        }

        if (! $query) {
            return ['ok' => true, 'error' => null, 'query' => null, 'products' => [], 'draft_text' => self::FALLBACK, 'source' => 'no_products_fallback'];
        }

        $search = $this->products->search($query, [], 5);
        $items = $search['items'] ?? [];

        if (! $search['ok'] || empty($items)) {
            return [
                'ok' => true,
                'error' => $search['ok'] ? null : ('WC search შეცდომა: '.($search['error'] ?? '?')),
                'query' => $query,
                'products' => [],
                'draft_text' => self::FALLBACK,
                'source' => 'no_products_fallback',
            ];
        }

        try {
            $composed = $this->composeProductReply($history->last(), $items);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $this->categorizeError($e), 'query' => $query, 'products' => $items, 'draft_text' => null, 'source' => 'extract_failed'];
        }

        if (! $this->validateProductReply($composed['reply'], $composed['product_ids'], $items)) {
            return [
                'ok' => true,
                'error' => null,
                'query' => $query,
                'products' => $items,
                'draft_text' => self::FALLBACK,
                'source' => 'validator_rejected',
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'query' => $query,
            'products' => $items,
            'draft_text' => $composed['reply'],
            'source' => 'wc_grounded',
        ];
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    private function lastInboundMessages(Conversation $c, int $n): \Illuminate\Support\Collection
    {
        return Message::where('conversation_id', $c->id)
            ->where('direction', Message::DIRECTION_IN)
            ->orderBy('created_at', 'desc')
            ->limit($n)
            ->pluck('body')
            ->reverse()
            ->values();
    }

    /**
     * One Claude/Haiku call: classify intent + extract search query.
     *
     * @return array{intent: string, query: ?string}
     */
    private function analyzeIntent(\Illuminate\Support\Collection $history): array
    {
        $system = <<<EOT
You analyze a customer chat for a Georgian electronics e-commerce site (gadget.ge).
Return ONLY valid JSON, no markdown, no commentary.

JSON shape:
{
  "intent": "product_query" | "greeting" | "general_question" | "complaint" | "order_status" | "price_inquiry" | "other",
  "query": "<2-3 keyword english/translit search query for WooCommerce, or null>"
}

QUERY RULES (CRITICAL):
- 2-3 keywords MAXIMUM (WooCommerce search is whole-string match — too many words returns 0 results)
- ONLY include: brand, model, product type (e.g. "iphone 15 case", "samsung headphones", "smart watch")
- NEVER include adjectives like: good, quality, cheap, best, premium, nice, ხარისხიანი, კარგი, იაფი, ფასიანი
- NEVER include filler words: please, want, need, "i need", "do you have", მინდა, გვაქვს, გაქვთ

product_query = customer asks about a SPECIFIC product, accessory, model.
price_inquiry alone (without specific product) = general.

Examples:
"გამარჯობა" → {"intent":"greeting","query":null}
"აიფონ 15-ის კარგი ქეისი მინდა" → {"intent":"product_query","query":"iphone 15 case"}    // "კარგი" dropped
"სამსუნგი s24-ის ეკრანის დაცვა გაქვთ?" → {"intent":"product_query","query":"samsung s24 screen"}
"მინდა ხარისხიანი ყურსასმენი ფასიანი" → {"intent":"product_query","query":"headphones"}   // adjectives dropped
"რა საათები გვაქვს" → {"intent":"product_query","query":"smart watch"}
"ფასები" → {"intent":"price_inquiry","query":null}
"ჩემი ორდერი სად არის?" → {"intent":"order_status","query":null}
EOT;

        $user = "ბოლო კლიენტის მესიჯ(ებ)ი:\n".$history->implode("\n---\n");

        $raw = $this->claude->complete(system: $system, user: $user, light: true);
        $raw = preg_replace('/^```(?:json)?\s*|```\s*$/m', '', trim($raw));
        $parsed = json_decode($raw, true);

        if (! is_array($parsed) || empty($parsed['intent'])) {
            return ['intent' => 'other', 'query' => null];
        }
        return [
            'intent' => (string) $parsed['intent'],
            'query' => ! empty($parsed['query']) ? (string) $parsed['query'] : null,
        ];
    }

    /**
     * Compose Georgian product reply grounded in real WC products.
     *
     * @param array $items raw WC product results
     * @return array{reply: string, product_ids: int[]}
     */
    private function composeProductReply(string $customerMessage, array $items, ?array $memory = null): array
    {
        // Reduce to the safe fields Claude needs
        $catalog = array_map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
            'price' => $p['price'],
            'stock' => $p['stock_status'],
            'category' => $p['categories'][0] ?? null,
        ], $items);

        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

        // Memory context (preferred brand, device, price range, etc.)
        $memoryText = '';
        if ($memory && ! empty($memory)) {
            $memBlock = [];
            if (! empty($memory['preferred_brand'])) $memBlock[] = "ბრენდი: ".$memory['preferred_brand'];
            if (! empty($memory['device_model'])) $memBlock[] = "მოწყობილობა: ".$memory['device_model'];
            if (! empty($memory['budget'])) $memBlock[] = "ბიუჯეტი: ".$memory['budget'];
            if (! empty($memory['previous_interest'])) $memBlock[] = "წინა ინტერესი: ".$memory['previous_interest'];
            if ($memBlock) {
                $memoryText = "\n\nკლიენტის შესახებ რაც ვიცი (გამოიყენე ბუნებრივად, ნუ გაიმეორებ):\n- ".implode("\n- ", $memBlock);
            }
        }

        $system = <<<EOT
შენ ხარ gadget.ge-ის გაყიდვების კონსულტანტი. ქართულ ჩატში პასუხობ — როგორც ცოცხალი თანამშრომელი მაღაზიის ფიზიკურ ფილიალში: თბილად, ბუნებრივად, ცოცხალი ენით.

ENGLISH-ART RULES, NOT ROBOT:
❌ "პროდუქტი ხელმისაწვდომია"
✅ "ეს მოდელი გვაქვს კი"

❌ "Would you like to purchase?"
✅ "თუ გინდა, ახლავე გადაგინახავ"

❌ "გთხოვთ მიუთითოთ..."
✅ "მითხარი ერთი — ..."

წესები (კრიტიკული):
- გამოიყენე მხოლოდ პროდუქტი ქვემოთ JSON სიიდან
- არცერთი სხვა ბრენდი/მოდელი/ფასი/მარაგი ნუ მოიგონებ
- 1-3 პროდუქტი ახსენე — არ ჩამოწერო ყველა ერთად
- ფასი — ისე როგორც სიაშია (ლარით ბოლოს)
- მარაგი — მარტო "გვაქვს" / "მარაგი ცარიელია"; საუბრის ჯაჭვი არ შეადეგო
- 1-3 წინადადება, არც სტრუქტურირდი — bullet points არ გამოიყენო
- emoji მხოლოდ თუ კლიენტმა გამოიყენა (გამონაკლისი: 👍 ერთხელ თუ რჩევას აძლევ — შესაძლოა)
- arc URL, არც ფილიალის სახელი, არც დიდი დაპირება ("რა თქმა უნდა, ნებისმიერ დროს")
- გადახდა / პოლისი / შრიფტი ნუ ჩასვამ თუ კლიენტმა არ ჰკითხა
- მისალოცი ფრაზა ("გამარჯობა") მხოლოდ თუ მესიჯი პირველ-პირველი ცარგი ხელახლა-შემოსვლაა

TONE EXAMPLES (Georgian retail natural):
- "iPhone 15-ის ქეისი ფინევოვენი გვაქვს კი, 30 ლარად. magsafe-ც აქვს."
- "ეარპოდსიდან i200 და iNpods 12 გვაქვს — 45-დან 70 ლარამდე. რომელ მოდელზე გაინტერესებს?"
- "200 ლარის ფარგლებში სამი ვარიანტი მაქვს — REMAX, JBL... რომელი ფერი გინდა?"

ხელმისაწვდომი პროდუქტები (real WC):
{$catalogJson}{$memoryText}

Output STRICT JSON only, no markdown:
{"reply": "<ქართული პასუხი 1-3 წინადადებაში>", "product_ids": [<id>...], "upsell_hint": "<optional 1-sentence soft upsell suggesting accessory category, or empty string>"}

product_ids — ჩამოთვალე reply-ში ნახსენები id-ები.
EOT;

        $user = "კლიენტმა მითხრა: \"".$customerMessage."\"\n\nდააწერე ბუნებრივი ქართული პასუხი (გონივრულად, ცოცხალი ენით):";

        $raw = $this->claude->complete(system: $system, user: $user, light: true);
        $raw = preg_replace('/^```(?:json)?\s*|```\s*$/m', '', trim($raw));
        $parsed = json_decode($raw, true);

        if (! is_array($parsed) || empty($parsed['reply'])) {
            throw new RuntimeException('Claude did not return JSON: '.mb_substr($raw, 0, 200));
        }

        return [
            'reply' => trim((string) $parsed['reply']),
            'product_ids' => array_map('intval', (array) ($parsed['product_ids'] ?? [])),
            'upsell_hint' => trim((string) ($parsed['upsell_hint'] ?? '')),
        ];
    }

    /**
     * General reply for non-product intent. Strictly forbids product mentions.
     */
    private function composeGeneralReply(Conversation $conv, \Illuminate\Support\Collection $history, ?array $memory = null): string
    {
        $custName = $conv->customer?->display_name ?? 'Customer';
        $platform = $conv->platform;
        $historyText = $history->take(-3)->implode("\n---\n");

        $memoryText = '';
        if ($memory && ! empty($memory)) {
            $bits = [];
            if (! empty($memory['preferred_brand'])) $bits[] = $memory['preferred_brand'];
            if (! empty($memory['device_model'])) $bits[] = $memory['device_model'];
            if (! empty($memory['previous_interest'])) $bits[] = 'წინა ჯერზე გაინტერესებდა: '.$memory['previous_interest'];
            if ($bits) $memoryText = "\n\nადრე ნახული: ".implode(' / ', $bits);
        }

        $system = <<<EOT
შენ ხარ gadget.ge-ის გაყიდვების კონსულტანტი. ცოცხალი ენით, ბუნებრივად პასუხობ Georgian retail style-ით — როგორც ნამდვილი თანამშრომელი.

TONE EXAMPLES:
- "გამარჯობა! რა გჭირდება, რა მოვძებნოთ?"
- "მითხარი ერთი, რომელ მოდელზე გაინტერესებს და ფერი?"
- "ოო, ცოტა ხანში გადავამოწმებ ვერ-ნახულ პოზიციას და მოგწერ."

წესები — ძალიან მნიშვნელოვანი:
- პასუხი ქართულად, 1-3 წინადადება, ცოცხალი
- **არცერთი კონკრეტული პროდუქტი / ფასი / მარაგი / ბრენდი / მოდელი** ნუ ახსენებ
- თუ კლიენტი პროდუქტს ეძებს — ჰკითხე დაკონკრეტება ("რომელი მოდელისთვის?", "რა მაგრამ?"); ბრენდს თვითონ ნუ ახსენებ
- ნუ გამოიყენებ ფორმალურ ფრაზებს: "გთხოვთ", "სანდო ვართ", "ხელმისაწვდომი", "მზად ვართ თქვენი მომსახურებისთვის"
- arc ფილიალის სახელი / მისამართი / დიდი დაპირება / URL
- emoji მხოლოდ თუ კლიენტმა გამოიყენა

კონტექსტი:
- პლატფორმა: {$platform}
- კლიენტი: {$custName}{$memoryText}

გამოიყენე მხოლოდ პასუხის ტექსტი, არცერთი markdown/მეტა.
EOT;

        $userPrompt = "ბოლო კონვერსაცია:\n{$historyText}\n\nშენი პასუხი (ცოცხალი, ბუნებრივი):";

        return trim($this->claude->complete(system: $system, user: $userPrompt, light: true));
    }

    /**
     * Validator. Rejects the reply if:
     *   - cited product_ids are not all in the provided WC items list
     *   - the reply text mentions any brand/model not present in WC items
     */
    private function validateProductReply(string $reply, array $citedIds, array $items): bool
    {
        $allowedIds = array_map(fn ($p) => (int) $p['id'], $items);
        foreach ($citedIds as $id) {
            if (! in_array((int) $id, $allowedIds, true)) {
                return false;
            }
        }

        // Gather all brand-like tokens that DO appear in our WC items
        // (case-insensitive) so we don't flag legitimate mentions.
        $wcText = mb_strtolower(implode(' ', array_map(fn ($p) => $p['name'].' '.($p['categories'][0] ?? ''), $items)));
        $replyLower = mb_strtolower($reply);

        foreach (self::BRAND_TOKENS as $tok) {
            if (str_contains($replyLower, $tok) && ! str_contains($wcText, $tok)) {
                // Reply mentions a brand we did NOT pass — reject.
                return false;
            }
        }

        return true;
    }

    /**
     * Detect brand/model leak in a "general" reply where we passed no
     * products at all. Any brand mention is forbidden in this path.
     */
    private function mentionsAnyBrand(string $text): bool
    {
        $low = mb_strtolower($text);
        foreach (self::BRAND_TOKENS as $tok) {
            if (str_contains($low, $tok)) {
                return true;
            }
        }
        return false;
    }

    private function blank(string $error, string $source = 'error'): array
    {
        return [
            'ok' => false,
            'suggestion' => null,
            'error' => $error,
            'model' => null,
            'source' => $source,
            'intent' => null,
            'query' => null,
            'products' => [],
        ];
    }

    private function categorizeError(Throwable $e): string
    {
        $msg = $e->getMessage();
        $low = strtolower($msg);

        if (str_contains($low, 'anthropic_api_key is not configured') || str_contains($low, 'is not configured')) {
            return 'ANTHROPIC_API_KEY არ არის ჩაწერილი — გადადი /admin/integrations → AI და შეინახე key';
        }
        if (str_contains($low, 'authentication') || str_contains($low, 'invalid_api_key') || str_contains($low, 'invalid x-api-key')) {
            return 'API key არასწორი ან გაუქმდა — გადააგენერირე console.anthropic.com → Settings → API Keys';
        }
        if (str_contains($low, 'credit') || str_contains($low, 'balance') || str_contains($low, 'insufficient')) {
            return 'Anthropic account-ზე კრედიტი ცარიელია — შეავსე billing-ში';
        }
        if (str_contains($low, '429') || str_contains($low, 'rate') || str_contains($low, 'too many')) {
            return 'Rate limit — ცოტა ხანში სცადე ან Anthropic tier-ი აიწიე';
        }
        if (str_contains($low, 'model') && (str_contains($low, 'not found') || str_contains($low, 'unavailable') || str_contains($low, 'does not exist'))) {
            return 'არასწორი model — შეცვალე /admin/integrations → AI → Primary/Light Model';
        }
        if (str_contains($low, 'connect') || str_contains($low, 'timeout') || str_contains($low, 'resolve')) {
            return 'ქსელის შეცდომა — სერვერი ვერ ცდილობს api.anthropic.com-ის';
        }
        return 'Claude შეცდომა: '.$msg;
    }
}
