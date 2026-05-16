<?php

namespace App\Services\Gadget;

use App\Models\AuditLog;
use App\Models\Order;
use App\Services\Gadget\Exceptions\WooApiException;
use App\Services\Gadget\Mappers\OrderMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a confirmed Order to WooCommerce. Idempotent: if the order
 * already has `external_order_id` set, the second push is a no-op (we
 * never create duplicates).
 */
class OrderPush
{
    public function __construct(
        private GadgetApi $api,
        private OrderMapper $mapper,
        private CustomerLink $customerLink,
    ) {}

    public function push(Order $order): array
    {
        if (! $this->api->isConfigured()) {
            return ['ok' => false, 'reason' => 'wc_not_configured'];
        }
        if ($order->external_order_id) {
            return ['ok' => true, 'reused' => true, 'external_order_id' => $order->external_order_id];
        }

        try {
            // Ensure WC customer exists / is linked.
            $customer = $order->customer;
            $wcCustomerId = $customer ? $this->customerLink->syncToWoo($customer, $order) : null;

            $payload = $this->mapper->toWoo($order);
            if ($wcCustomerId) {
                $payload['customer_id'] = $wcCustomerId;
            }

            $created = $this->api->orders()->create($payload);
            if (empty($created['id'])) {
                return ['ok' => false, 'reason' => 'wc_no_id', 'detail' => $created];
            }

            DB::transaction(function () use ($order, $created) {
                $order->update([
                    'external_order_id'    => (string) $created['id'],
                    'payment_provider_ref' => $order->payment_provider_ref ?: ($created['order_key'] ?? null),
                ]);
                AuditLog::record('system', 'order.pushed_to_woo', 'orders', $order->id, [
                    'wc_order_id' => $created['id'],
                ]);
            });

            return [
                'ok'                => true,
                'external_order_id' => (string) $created['id'],
                'wc_status'         => $created['status'] ?? null,
            ];
        } catch (WooApiException $e) {
            Log::error('gadget.order_push.failed', ['order' => $order->id, 'status' => $e->status, 'msg' => $e->getMessage(), 'body' => $e->body]);
            return ['ok' => false, 'reason' => 'wc_api_error', 'detail' => $e->getMessage()];
        }
    }
}
