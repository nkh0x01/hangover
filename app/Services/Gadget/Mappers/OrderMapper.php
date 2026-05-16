<?php

namespace App\Services\Gadget\Mappers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Arr;

class OrderMapper
{
    public function toWoo(Order $order): array
    {
        $cfg = (array) config('gadget.orders');

        $billing = array_filter([
            'first_name' => $this->firstName($order->customer_name),
            'last_name'  => $this->lastName($order->customer_name),
            'phone'      => $order->customer_phone,
            'city'       => $order->city,
            'address_1'  => $order->address,
            'country'    => 'GE',
            'email'      => ($order->customer_phone ?? 'unknown') . '@gadget-chat.local',
        ]);

        $items = [];
        foreach ((array) $order->items_json as $i) {
            $product = Product::where('sku', $i['sku'] ?? null)->first();
            if (! $product || ! $product->source_id) {
                continue;
            }
            $items[] = array_filter([
                'product_id' => (int) $product->source_id,
                'quantity'   => (int) ($i['qty'] ?? 1),
                'subtotal'   => (string) ($i['price'] ?? null),
                'total'      => isset($i['price'], $i['qty']) ? (string) ($i['price'] * $i['qty']) : null,
            ]);
        }

        $shippingLines = $this->shippingLines($order, $cfg);

        $pmCfg = Arr::get($cfg, "payment_methods." . ($order->payment_method ?? 'branch'), [
            'id' => 'cod', 'title' => 'ფილიალში გადახდა', 'set_paid' => false,
        ]);

        return [
            'payment_method'       => $pmCfg['id'],
            'payment_method_title' => $pmCfg['title'],
            'set_paid'             => (bool) ($pmCfg['set_paid'] ?? false),
            'status'               => 'pending',
            'currency'             => $order->currency ?: ($cfg['currency_fallback'] ?? 'GEL'),
            'billing'              => $billing,
            'shipping'             => $billing,
            'line_items'           => $items,
            'shipping_lines'       => $shippingLines,
            'customer_note'        => trim((string) $order->notes),
            'meta_data'            => [
                ['key' => $cfg['source_meta_key'] ?? 'created_via', 'value' => $cfg['source_meta_value'] ?? 'gadget_ai_chatbot'],
                ['key' => 'gadget_chatbot_order_id',                'value' => (string) $order->id],
                ['key' => 'gadget_chatbot_conversation_id',         'value' => (string) ($order->conversation_id ?? '')],
                ['key' => 'gadget_chatbot_branch',                  'value' => (string) ($order->preferred_branch ?? '')],
            ],
        ];
    }

    private function shippingLines(Order $order, array $cfg): array
    {
        $shipping = $cfg['shipping'] ?? [];
        return match ($order->delivery_method) {
            'pickup' => [[
                'method_id'    => $shipping['pickup_method_id'] ?? 'local_pickup',
                'method_title' => $shipping['pickup_title']     ?? 'Pickup',
                'total'        => '0.00',
            ]],
            'courier', 'cod' => [[
                'method_id'    => $shipping['courier_method_id'] ?? 'flat_rate',
                'method_title' => $shipping['courier_title']     ?? 'Courier',
                'total'        => number_format((float) ($order->delivery_fee ?: ($shipping['courier_fee'] ?? 0)), 2, '.', ''),
            ]],
            default => [],
        };
    }

    private function firstName(?string $full): string
    {
        if (! $full) return '';
        $p = explode(' ', trim($full), 2);
        return $p[0];
    }

    private function lastName(?string $full): string
    {
        if (! $full) return '';
        $p = explode(' ', trim($full), 2);
        return $p[1] ?? '';
    }
}
