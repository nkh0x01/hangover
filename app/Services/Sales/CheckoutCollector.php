<?php

namespace App\Services\Sales;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;

/**
 * Tiny state machine over the `orders` table. Knows what's needed for
 * a draft to become confirmable, and accepts partial updates from
 * Claude's `create_order_draft` tool.
 */
class CheckoutCollector
{
    private const REQUIRED = [
        'customer_name', 'customer_phone', 'delivery_method',
    ];

    public function upsertDraft(Conversation $conversation, Customer $customer, array $input): Order
    {
        $order = Order::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', Order::STATUS_DRAFT)
            ->latest('id')
            ->first();

        if (! $order) {
            $order = new Order([
                'conversation_id' => $conversation->id,
                'customer_id'     => $customer->id,
                'status'          => Order::STATUS_DRAFT,
                'items_json'      => [],
                'currency'        => 'GEL',
            ]);
        }

        // Items
        if (! empty($input['items']) && is_array($input['items'])) {
            $items = $this->normaliseItems($input['items']);
            $order->items_json = $items;
            $order->subtotal = $this->subtotal($items);
        }

        // Address / delivery
        foreach (['customer_name','customer_phone','city','address','preferred_branch','delivery_method','payment_method','notes'] as $f) {
            if (! empty($input[$f])) {
                $order->$f = $input[$f];
            }
        }

        // Delivery fee
        $order->delivery_fee = match ($order->delivery_method) {
            'courier' => 10.00,
            default   => 0.00,
        };
        $order->total = (float) $order->subtotal + (float) $order->delivery_fee;

        $order->save();

        // Bump conversation/lead state.
        $conversation->update(['lead_status' => Conversation::STATUS_ORDER_CREATED]);
        Lead::updateOrCreate(
            ['conversation_id' => $conversation->id, 'status' => 'order_created'],
            [
                'customer_id'       => $customer->id,
                'product_skus_json' => array_column($order->items_json ?? [], 'sku'),
                'status'            => 'order_created',
                'last_event_at'     => now(),
            ],
        );

        return $order;
    }

    public function missingFields(Order $order): array
    {
        $missing = [];
        foreach (self::REQUIRED as $f) {
            if (empty($order->$f)) {
                $missing[] = $f;
            }
        }
        if (empty($order->items_json)) {
            $missing[] = 'items';
        }
        if (($order->delivery_method ?? null) === 'courier' && empty($order->address)) {
            $missing[] = 'address';
        }
        if (($order->delivery_method ?? null) === 'pickup' && empty($order->preferred_branch)) {
            $missing[] = 'preferred_branch';
        }
        return $missing;
    }

    public function confirm(Order $order): bool
    {
        if ($this->missingFields($order) !== []) {
            return false;
        }
        $order->update([
            'status'       => Order::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        return true;
    }

    private function normaliseItems(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $sku = $it['sku'] ?? null;
            if (! $sku) continue;
            $p = Product::where('sku', $sku)->first();
            if (! $p) continue;
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $out[] = [
                'sku'   => $p->sku,
                'name'  => $p->name,
                'qty'   => $qty,
                'price' => $p->effectivePrice(),
            ];
        }
        return $out;
    }

    private function subtotal(array $items): float
    {
        $s = 0.0;
        foreach ($items as $i) {
            $s += (float) $i['price'] * (int) $i['qty'];
        }
        return $s;
    }
}
