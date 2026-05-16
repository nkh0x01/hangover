<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Escalation\EscalationDispatcher;
use App\Services\Products\ProductCatalog;
use App\Services\Products\RecommendationEngine;
use App\Services\Sales\CheckoutCollector;
use App\Services\Sales\PaymentLinkGenerator;

/**
 * Defines the tools Claude can call inside a sales conversation and
 * executes them when Claude returns a tool_use block.
 */
class ToolRegistry
{
    public function __construct(
        private ProductCatalog $catalog,
        private RecommendationEngine $rec,
        private CheckoutCollector $checkout,
        private PaymentLinkGenerator $payments,
        private EscalationDispatcher $escalation,
    ) {}

    /** Tool schemas passed to Anthropic Messages API. */
    public function definitions(): array
    {
        return [
            [
                'name'        => 'search_products',
                'description' => 'Search the live Gadget catalog. Returns up to 5 in-stock products matching the query and filters.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query'    => ['type' => 'string', 'description' => 'Free-text query, e.g. "iphone 15 case clear"'],
                        'category' => ['type' => 'string', 'description' => 'Optional category filter (e.g. "cases", "chargers")'],
                        'brand'    => ['type' => 'string', 'description' => 'Optional brand filter'],
                        'min_price'=> ['type' => 'number'],
                        'max_price'=> ['type' => 'number'],
                        'in_stock' => ['type' => 'boolean', 'default' => true],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'check_stock',
                'description' => 'Check stock for a specific SKU, optionally per branch.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'sku'    => ['type' => 'string'],
                        'branch' => ['type' => 'string', 'description' => 'Branch name; null = total stock'],
                    ],
                    'required' => ['sku'],
                ],
            ],
            [
                'name'        => 'recommend_alternatives',
                'description' => 'Return alternatives in the same category and price band as the given SKU.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['sku' => ['type' => 'string']],
                    'required'   => ['sku'],
                ],
            ],
            [
                'name'        => 'create_order_draft',
                'description' => 'Create or update a draft order with whatever fields you have so far. Returns the missing fields list.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items'            => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['sku' => ['type'=>'string'], 'qty' => ['type'=>'integer']]]],
                        'customer_name'    => ['type' => 'string'],
                        'customer_phone'   => ['type' => 'string'],
                        'city'             => ['type' => 'string'],
                        'address'          => ['type' => 'string'],
                        'preferred_branch' => ['type' => 'string'],
                        'delivery_method'  => ['type' => 'string', 'enum' => ['pickup', 'courier', 'cod']],
                        'payment_method'   => ['type' => 'string', 'enum' => ['branch', 'card', 'cod']],
                        'notes'            => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name'        => 'generate_payment_link',
                'description' => 'Generate a secure card-payment link for an existing draft order. Only call when the customer explicitly chose card payment.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['order_id' => ['type' => 'integer']],
                    'required'   => ['order_id'],
                ],
            ],
            [
                'name'        => 'escalate_to_human',
                'description' => 'Hand the conversation to a human employee. Call this when confidence is low, the customer is angry, the request is out of scope, or any factual claim cannot be verified through other tools.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'reason'  => ['type' => 'string'],
                        'urgency' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        'summary' => ['type' => 'string'],
                    ],
                    'required' => ['reason', 'urgency'],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call returned by Claude. Returns the JSON-encodable
     * result that gets fed back to Claude as a tool_result block.
     */
    public function execute(string $name, array $input, Customer $customer, Conversation $conversation): array
    {
        return match ($name) {
            'search_products'         => $this->searchProducts($input),
            'check_stock'             => $this->checkStock($input),
            'recommend_alternatives'  => $this->recommendAlternatives($input),
            'create_order_draft'      => $this->createOrderDraft($input, $customer, $conversation),
            'generate_payment_link'   => $this->generatePaymentLink($input, $conversation),
            'escalate_to_human'       => $this->escalate($input, $customer, $conversation),
            default                   => ['error' => "unknown tool: $name"],
        };
    }

    private function searchProducts(array $i): array
    {
        $products = $this->catalog->search(
            query:    $i['query'] ?? '',
            category: $i['category'] ?? null,
            brand:    $i['brand'] ?? null,
            minPrice: $i['min_price'] ?? null,
            maxPrice: $i['max_price'] ?? null,
            inStock:  $i['in_stock'] ?? true,
            limit:    5,
        );

        return ['products' => array_map(fn ($p) => [
            'sku'     => $p->sku,
            'name'    => $p->name,
            'brand'   => $p->brand,
            'price'   => $p->effectivePrice(),
            'currency'=> $p->currency,
            'stock'   => $p->stock_total,
            'image'   => $p->primaryImage(),
            'url'     => $p->url,
            'attrs'   => $p->attributes_json ?? [],
        ], $products)];
    }

    private function checkStock(array $i): array
    {
        $product = $this->catalog->findBySku($i['sku'] ?? '');
        if (! $product) {
            return ['error' => 'sku_not_found'];
        }
        $branch = $i['branch'] ?? null;
        return [
            'sku'        => $product->sku,
            'in_stock'   => $product->isInStock($branch),
            'stock_total'=> $product->stock_total,
            'per_branch' => $product->stock_by_branch_json ?? [],
        ];
    }

    private function recommendAlternatives(array $i): array
    {
        $alts = $this->rec->alternativesFor($i['sku'] ?? '');
        return ['alternatives' => array_map(fn ($p) => [
            'sku'   => $p->sku,
            'name'  => $p->name,
            'price' => $p->effectivePrice(),
            'image' => $p->primaryImage(),
        ], $alts)];
    }

    private function createOrderDraft(array $i, Customer $customer, Conversation $conversation): array
    {
        $order = $this->checkout->upsertDraft($conversation, $customer, $i);
        return [
            'order_id'       => $order->id,
            'status'         => $order->status,
            'missing_fields' => $this->checkout->missingFields($order),
            'total'          => (float) $order->total,
        ];
    }

    private function generatePaymentLink(array $i, Conversation $conversation): array
    {
        $link = $this->payments->generate((int) ($i['order_id'] ?? 0), $conversation);
        return $link
            ? ['payment_link' => $link]
            : ['error' => 'cannot_generate_link'];
    }

    private function escalate(array $i, Customer $customer, Conversation $conversation): array
    {
        $this->escalation->dispatch(
            conversation: $conversation,
            customer:     $customer,
            reason:       $i['reason']  ?? 'ai_requested',
            urgency:      $i['urgency'] ?? 'medium',
            summary:      $i['summary'] ?? null,
        );

        return ['escalated' => true];
    }
}
