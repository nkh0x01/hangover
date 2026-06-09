<?php

namespace App\Services\Gadget;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Live WooCommerce product search wrapper. Maps the verbose WC product
 * response down to a consistent shape used by the admin inbox.
 *
 * Cached briefly (60s) per query to avoid hammering gadget.ge during
 * typing in the search box.
 */
class ProductSearchService
{
    public function __construct(
        private WooCommerceClient $wc,
        private KeywordMapper $keywords,
    ) {}

    /**
     * @return array{
     *   ok: bool, items: array, error: ?string, source: string,
     *   status: string,           // 'connected' | 'auth_failed' | 'blocked' | 'no_products' | 'error'
     *   queries_tried: string[],
     *   matched_query: ?string,   // the variant that actually returned results
     * }
     */
    public function search(string $query = '', array $filters = [], int $limit = 20): array
    {
        $query = trim($query);
        $category = $filters['category'] ?? null;
        $sku = $filters['sku'] ?? null;
        $cacheKey = 'wc.search.v2.'.md5($query.'|'.$category.'|'.$sku.'|'.$limit);

        return Cache::remember($cacheKey, 60, function () use ($query, $category, $sku, $limit) {
            // SKU fast path — numeric queries try sku= first
            if ($query !== '' && $this->keywords->looksLikeSku($query) && ! $sku) {
                $result = $this->runQuery(['sku' => $query, 'per_page' => $limit]);
                if ($result['status'] === 'connected' && ! empty($result['items'])) {
                    return $result + ['queries_tried' => [$query], 'matched_query' => $query, 'source' => 'wc_live_sku'];
                }
                // Fall through to text search
            }

            // Build search variants: original + Georgian-translated + fallback
            $variants = $query !== '' ? $this->keywords->expand($query) : [''];
            $queriesTried = [];
            $allItems = [];
            $seenIds = [];
            $matchedQuery = null;
            $lastStatus = 'no_products';
            $lastError = null;

            foreach ($variants as $variant) {
                $queriesTried[] = $variant;
                $params = ['per_page' => $limit, 'status' => 'publish', 'stock_status' => 'instock'];
                if ($variant !== '') $params['search'] = $variant;
                if ($category) $params['category'] = $category;
                if ($sku) $params['sku'] = $sku;

                $r = $this->runQuery($params);
                $lastStatus = $r['status'];
                $lastError = $r['error'];

                // Hard errors (auth_failed, blocked) — stop trying
                if (in_array($r['status'], ['auth_failed', 'blocked', 'error'], true)) {
                    return [
                        'ok' => false, 'items' => [], 'error' => $r['error'],
                        'source' => 'wc_live', 'status' => $r['status'],
                        'queries_tried' => $queriesTried, 'matched_query' => null,
                    ];
                }

                // Post-filter: WC's ?search= is loose (matches across SKU,
                // description, attributes), so a query for "iphone" can
                // return a power bank. Keep only products whose NAME
                // contains at least one significant token from the variant.
                $relevant = $this->postFilterByName($r['items'], $variant);

                foreach ($relevant as $item) {
                    if (isset($seenIds[$item['id']])) continue;
                    $seenIds[$item['id']] = true;
                    $allItems[] = $item;
                    if ($matchedQuery === null) $matchedQuery = $variant;
                    if (count($allItems) >= $limit * 2) break 2; // overfetch for ranking
                }
            }

            // Rank: in-stock first, then has-image, then name-token strength
            $allItems = $this->rankProducts($allItems, $query);

            // Mix price tiers if we have more than 3 results (budget / mid / premium)
            $allItems = $this->mixPriceTiers($allItems, $limit);

            return [
                'ok' => true,
                'items' => array_slice($allItems, 0, $limit),
                'error' => null,
                'source' => 'wc_live',
                'status' => empty($allItems) ? 'no_products' : 'connected',
                'queries_tried' => $queriesTried,
                'matched_query' => $matchedQuery,
            ];
        });
    }

    /**
     * Rank products by quality signals:
     *   1. in stock
     *   2. has image
     *   3. token match strength against original query
     *   4. higher stock quantity (proxy for popularity)
     */
    private function rankProducts(array $items, string $query): array
    {
        $queryTokens = array_filter(preg_split('/[\s\p{P}]+/u', mb_strtolower($query)) ?: []);
        usort($items, function ($a, $b) use ($queryTokens) {
            $sa = $this->productScore($a, $queryTokens);
            $sb = $this->productScore($b, $queryTokens);
            return $sb <=> $sa;
        });
        return $items;
    }

    private function productScore(array $p, array $queryTokens): float
    {
        $score = 0.0;
        if (($p['stock_status'] ?? '') === 'instock') $score += 100;
        if (! empty($p['image'])) $score += 30;
        // Name-token match strength
        $nameLower = mb_strtolower($p['name'] ?? '');
        foreach ($queryTokens as $t) {
            if (mb_strlen($t) < 3) continue;
            if (str_contains($nameLower, $t)) $score += 20;
        }
        // Stock quantity (capped, used as popularity proxy)
        $stock = (int) ($p['stock_quantity'] ?? 0);
        $score += min(20, $stock / 5);
        // Penalty for on-sale (often clearance items we don't always want to push first)
        // Actually NO — keep neutral
        return $score;
    }

    /**
     * Best-effort price tier mix. When we have 4+ results, return up to
     * `limit` with at least one from each tier (budget/mid/premium).
     */
    private function mixPriceTiers(array $items, int $limit): array
    {
        if (count($items) <= 3) return $items;

        $prices = array_map(fn ($p) => (float) ($p['price'] ?? 0), $items);
        $prices = array_filter($prices, fn ($p) => $p > 0);
        if (count($prices) < 2) return $items;

        sort($prices);
        $median = $prices[(int) (count($prices) / 2)];
        $p25 = $prices[(int) (count($prices) / 4)];
        $p75 = $prices[(int) (count($prices) * 3 / 4)];

        $budget = [];
        $mid = [];
        $premium = [];
        foreach ($items as $p) {
            $price = (float) ($p['price'] ?? 0);
            if ($price < $p25) $budget[] = $p;
            elseif ($price > $p75) $premium[] = $p;
            else $mid[] = $p;
        }

        // Take top 2 from each, dedupe, fall back to original ranking if short
        $mix = [];
        foreach ([$budget, $mid, $premium] as $tier) {
            foreach (array_slice($tier, 0, 2) as $p) {
                $mix[$p['id']] = $p;
            }
        }
        foreach ($items as $p) {
            if (count($mix) >= $limit) break;
            $mix[$p['id']] = $p;
        }
        return array_values($mix);
    }

    /**
     * Post-filter WC results: keep only products whose NAME contains at
     * least one significant token from the search variant. Eliminates the
     * "iphone → power bank" relevance bug from WC's loose search.
     *
     * @param array $items product list from mapProduct
     * @param string $variant the search variant that produced these results
     * @return array filtered list (may be empty)
     */
    private function postFilterByName(array $items, string $variant): array
    {
        if ($variant === '') return $items;

        // Tokenize variant; keep only words >= 3 chars or 2+ digits
        $tokens = preg_split('/[\s\p{P}]+/u', mb_strtolower(trim($variant))) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => mb_strlen($t) >= 3 || preg_match('/^\d{2,}$/', $t)));
        if (empty($tokens)) return $items;

        $out = [];
        foreach ($items as $item) {
            $hay = mb_strtolower(($item['name'] ?? '').' '.($item['sku'] ?? '').' '.implode(' ', $item['categories'] ?? []));
            foreach ($tokens as $tok) {
                if (str_contains($hay, $tok)) {
                    $out[] = $item;
                    continue 2;
                }
            }
        }
        return $out;
    }

    /**
     * Execute one WC request and classify the response.
     *
     * @return array{items: array, status: string, error: ?string}
     */
    private function runQuery(array $params): array
    {
        try {
            $raw = $this->wc->get('products', $params);

            if (! is_array($raw)) {
                return ['items' => [], 'status' => 'error', 'error' => 'non_array_response'];
            }
            // HTML response (raw key set by client when JSON decode fails)
            if (isset($raw['raw'])) {
                $body = (string) $raw['raw'];
                $lc = strtolower($body);
                if (str_contains($lc, 'cloudflare') || str_contains($lc, 'security')) {
                    return ['items' => [], 'status' => 'blocked', 'error' => 'security_block_html_response'];
                }
                if (str_contains($lc, 'wp-login') || str_contains($lc, 'login_form')) {
                    return ['items' => [], 'status' => 'auth_failed', 'error' => 'wp_login_redirect'];
                }
                return ['items' => [], 'status' => 'error', 'error' => 'non_json_response'];
            }
            // WC error envelope
            if (isset($raw['code']) && isset($raw['message'])) {
                $code = (string) $raw['code'];
                $msg = (string) $raw['message'];
                $status = 'error';
                if (str_contains($code, 'authentication') || str_contains($code, 'cannot_view') || str_contains($code, 'invalid_key')) {
                    $status = 'auth_failed';
                } elseif (str_contains($code, 'no_route') || str_contains($code, 'invalid_term')) {
                    $status = 'error';
                }
                return ['items' => [], 'status' => $status, 'error' => $code.': '.$msg];
            }
            // Real array of products
            $valid = array_values(array_filter($raw, fn ($p) => is_array($p) && isset($p['id'])));
            $items = array_map(fn ($p) => $this->mapProduct($p), $valid);
            return ['items' => $items, 'status' => empty($items) ? 'no_products' : 'connected', 'error' => null];
        } catch (Throwable $e) {
            return ['items' => [], 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Map raw WC product → admin-friendly shape.
     *
     * @return array{
     *   id: int, sku: ?string, name: string, price: string,
     *   regular_price: ?string, sale_price: ?string, on_sale: bool,
     *   currency: string, stock_status: string, stock_quantity: ?int,
     *   short_description: ?string, permalink: ?string, image: ?string,
     *   images: array, categories: array
     * }
     */
    private function mapProduct(array $p): array
    {
        $images = array_map(fn ($img) => $img['src'] ?? null, $p['images'] ?? []);
        $images = array_values(array_filter($images));

        return [
            'id' => (int) ($p['id'] ?? 0),
            'sku' => $p['sku'] ?? null,
            'name' => (string) ($p['name'] ?? ''),
            'price' => (string) ($p['price'] ?? ''),
            'regular_price' => $p['regular_price'] ?? null,
            'sale_price' => $p['sale_price'] ?? null,
            'on_sale' => (bool) ($p['on_sale'] ?? false),
            'currency' => 'GEL',
            'stock_status' => (string) ($p['stock_status'] ?? 'instock'),
            'stock_quantity' => isset($p['stock_quantity']) ? (int) $p['stock_quantity'] : null,
            'short_description' => $this->stripHtml($p['short_description'] ?? ''),
            'description' => $this->stripHtml($p['description'] ?? ''),
            'permalink' => $p['permalink'] ?? null,
            'image' => $images[0] ?? null,
            'images' => $images,
            'categories' => array_map(fn ($c) => $c['name'] ?? null, $p['categories'] ?? []),
        ];
    }

    private function stripHtml(string $s): string
    {
        $s = html_entity_decode(strip_tags($s));
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * Format one product as a customer-facing Messenger text message.
     * Pulls only facts from $product (no inventions). NO website URL —
     * link is for internal admin reference only, not main customer flow.
     */
    public function formatForChat(array $product): string
    {
        $name = $product['name'];
        $price = $product['price'];
        $regular = $product['regular_price'];
        $onSale = $product['on_sale'];
        $stock = $product['stock_status'] === 'instock' ? 'მარაგშია' : 'მარაგი ცარიელია';
        $short = $product['short_description'] ?: substr((string) ($product['description'] ?? ''), 0, 140);
        $short = mb_substr($short, 0, 160);

        $priceLine = $onSale && $regular
            ? "ფასი: {$price} ₾ (ძველი ფასი {$regular} ₾)"
            : "ფასი: {$price} ₾";

        $lines = [$name, $priceLine, $stock];

        if ($short) {
            $lines[] = '';
            $lines[] = $short;
        }

        $lines[] = '';
        $lines[] = 'გადახდა: ნაღდი / ბარათით / ფილიალში';

        return implode("\n", $lines);
    }
}
