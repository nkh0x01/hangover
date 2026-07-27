<?php

namespace App\Services\Gadget;

use App\Models\AuditLog;
use App\Models\Order;
use App\Services\Gadget\Mappers\OrderMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes a confirmed Order to the gadget.ge Order API (new Laravel site).
 * Idempotent: the API dedupes on external_id, and we skip locally once the
 * order has external_order_id.
 */
class OrderPush
{
    public function __construct(
        private CatalogApiClient $api,
        private OrderMapper $mapper,
    ) {}

    public function push(Order $order): array
    {
        if (! $this->api->isConfigured()) {
            return ['ok' => false, 'reason' => 'catalog_api_not_configured'];
        }
        if ($order->external_order_id) {
            return ['ok' => true, 'reused' => true, 'external_order_id' => $order->external_order_id];
        }

        try {
            $payload = $this->mapper->toApi($order);
            $created = $this->api->createOrder($payload);

            if (! $created || empty($created['id'])) {
                Log::warning('gadget.order_push.no_id', ['order' => $order->id, 'resp' => $created]);

                return ['ok' => false, 'reason' => 'api_no_id', 'detail' => $created];
            }

            DB::transaction(function () use ($order, $created) {
                $order->update(['external_order_id' => (string) $created['id']]);
                AuditLog::record('system', 'order.pushed_to_gadget', 'orders', $order->id, [
                    'gadget_order_id' => $created['id'],
                    'number' => $created['number'] ?? null,
                    'status' => $created['status'] ?? null,
                ]);
            });

            return [
                'ok' => true,
                'external_order_id' => (string) $created['id'],
                'number' => $created['number'] ?? null,
                'status' => $created['status'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('gadget.order_push.failed', ['order' => $order->id, 'msg' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'exception', 'detail' => $e->getMessage()];
        }
    }
}
