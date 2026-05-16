<?php

namespace App\Services\Sales;

use App\Models\Conversation;
use App\Models\Order;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates a card-payment link for a draft order via the configured
 * PSP. Default driver: Bank of Georgia (BOG) e-commerce. Pluggable by
 * setting PAYMENT_PROVIDER and adding a matching `createOrder*` method.
 */
class PaymentLinkGenerator
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 20, 'http_errors' => false]);
    }

    public function generate(int $orderId, Conversation $conversation): ?string
    {
        $order = Order::find($orderId);
        if (! $order || $order->conversation_id !== $conversation->id) {
            return null;
        }
        if (in_array($order->status, [Order::STATUS_PAID, Order::STATUS_REFUNDED], true)) {
            return $order->payment_link;
        }

        $provider = config('payments.provider', 'stub');

        try {
            $link = match ($provider) {
                'bog'    => $this->createOrderBog($order),
                'tbc'    => $this->createOrderTbc($order),
                default  => $this->createOrderStub($order),
            };
        } catch (Throwable $e) {
            Log::error('payment.link.failed', ['provider' => $provider, 'order' => $orderId, 'msg' => $e->getMessage()]);
            return null;
        }

        if (! $link) {
            return null;
        }

        $order->update([
            'payment_link'   => $link,
            'payment_method' => 'card',
            'payment_status' => 'pending',
        ]);
        $conversation->update(['lead_status' => Conversation::STATUS_PAYMENT_PENDING]);
        return $link;
    }

    private function createOrderBog(Order $order): ?string
    {
        $key    = config('payments.api_key');
        $secret = config('payments.api_secret');
        if (! $key || ! $secret) {
            return $this->createOrderStub($order);
        }

        $auth = $this->http->post(config('payments.bog.oauth_url'), [
            'auth'        => [$key, $secret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);
        $token = json_decode((string) $auth->getBody(), true)['access_token'] ?? null;
        if (! $token) return null;

        $payload = [
            'callback_url'      => config('payments.callback_url') ?: url('/payments/bog/callback'),
            'external_order_id' => (string) $order->id,
            'purchase_units' => [
                'currency'     => $order->currency ?: 'GEL',
                'total_amount' => (float) $order->total,
                'basket'       => array_map(fn ($i) => [
                    'quantity'    => $i['qty'],
                    'unit_price'  => $i['price'],
                    'product_id'  => $i['sku'],
                    'description' => $i['name'],
                ], $order->items_json ?? []),
            ],
            'redirect_urls' => [
                'success' => config('payments.return_url'),
                'fail'    => config('payments.fail_url'),
            ],
        ];

        $create = $this->http->post(config('payments.bog.orders_url'), [
            'headers' => ['Authorization' => "Bearer $token", 'Content-Type' => 'application/json'],
            'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $body = json_decode((string) $create->getBody(), true) ?: [];
        $order->payment_provider_ref = $body['id'] ?? null;

        return $body['_links']['redirect']['href'] ?? null;
    }

    private function createOrderTbc(Order $order): ?string
    {
        // Stub — implement when TBC creds available.
        return $this->createOrderStub($order);
    }

    private function createOrderStub(Order $order): string
    {
        // Test/dev stub: returns a deterministic placeholder URL.
        $token = Str::random(24);
        $order->payment_provider_ref = $token;
        return url("/payments/stub/$token?order={$order->id}");
    }
}
