<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Services\Gadget\CatalogSync;
use App\Services\Gadget\CouponSync;
use App\Services\Gadget\Mappers\ProductMapper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Receives gadget.ge → us push notifications. Configure the WooCommerce
 * webhooks at /wp-admin/admin.php?page=wc-settings&tab=advanced&section=webhooks
 *
 * Recommended subscriptions:
 *   - product.updated   → keep stock/price mirror fresh
 *   - product.deleted   → deactivate locally
 *   - order.updated     → bubble back status changes (paid, fulfilled)
 *   - coupon.updated    → refresh coupons
 */
class GadgetWebhookController extends Controller
{
    public function __construct(
        private CatalogSync $catalogSync,
        private ProductMapper $productMapper,
    ) {}

    public function handle(Request $request)
    {
        $topic = $request->header('X-WC-Webhook-Topic', 'unknown');
        $body = $request->json()->all() ?: [];

        Log::info('gadget.webhook', ['topic' => $topic, 'id' => $body['id'] ?? null]);
        AuditLog::record('system', 'gadget.webhook.received', null, null, ['topic' => $topic, 'id' => $body['id'] ?? null]);

        try {
            match (true) {
                str_starts_with($topic, 'product.') => $this->handleProduct($topic, $body),
                str_starts_with($topic, 'order.') => $this->handleOrder($topic, $body),
                str_starts_with($topic, 'coupon.') => $this->handleCoupon($topic, $body),
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);
        }

        return response('ok', 200);
    }

    private function handleProduct(string $topic, array $body): void
    {
        if ($topic === 'product.deleted') {
            if (! empty($body['id'])) {
                Product::where('source_id', (string) $body['id'])->update(['is_active' => false, 'stock_total' => 0]);
            }

            return;
        }

        // created / updated / restored → upsert.
        $row = $this->productMapper->fromWoo($body);
        if (! $row['sku']) {
            $row['sku'] = 'wc-' . $row['source_id'];
        }
        Product::updateOrCreate(['sku' => $row['sku']], $row);
    }

    private function handleOrder(string $topic, array $body): void
    {
        $wcId = (string) ($body['id'] ?? '');
        if ($wcId === '') {
            return;
        }

        $order = Order::where('external_order_id', $wcId)->first();
        if (! $order) {
            return;
        } // not one of ours

        $status = $body['status'] ?? null;
        $paid = in_array($status, ['processing', 'completed'], true);

        $order->update(array_filter([
            'status' => $this->mapStatus($status),
            'payment_status' => $paid ? 'paid' : $order->payment_status,
            'paid_at' => $paid && ! $order->paid_at ? now() : $order->paid_at,
            'fulfilled_at' => $status === 'completed' && ! $order->fulfilled_at ? now() : $order->fulfilled_at,
        ], fn ($v) => $v !== null));
    }

    private function handleCoupon(string $topic, array $body): void
    {
        // Cheaper to re-sync the one row than to special-case it.
        // CouponSync uses code as the unique key; safe to call.
        app(CouponSync::class)->run();
    }

    private function mapStatus(?string $wc): ?string
    {
        return match ($wc) {
            'pending' => Order::STATUS_DRAFT,
            'on-hold' => Order::STATUS_CONFIRMED,
            'processing' => Order::STATUS_PAID,
            'completed' => Order::STATUS_FULFILLED,
            'cancelled' => Order::STATUS_CANCELLED,
            'refunded' => Order::STATUS_REFUNDED,
            default => null,
        };
    }
}
