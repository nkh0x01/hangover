<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Sales\CheckoutCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_upserts_a_draft_and_reports_missing_fields(): void
    {
        Product::create(['sku' => 'TEST-1', 'name' => 'Test product', 'category' => 'cases', 'price' => 50, 'stock_total' => 5, 'is_active' => true]);
        $customer = Customer::create(['platform' => 'whatsapp', 'platform_user_id' => '995500000001']);
        $conv = Conversation::create(['customer_id' => $customer->id, 'platform' => 'whatsapp', 'thread_id' => 'tt']);

        $collector = new CheckoutCollector();

        $order = $collector->upsertDraft($conv, $customer, [
            'items' => [['sku' => 'TEST-1', 'qty' => 2]],
        ]);

        $this->assertSame(100.0, (float) $order->subtotal);

        $missing = $collector->missingFields($order);
        $this->assertContains('customer_name', $missing);
        $this->assertContains('customer_phone', $missing);
        $this->assertContains('delivery_method', $missing);

        $collector->upsertDraft($conv, $customer, [
            'customer_name'    => 'ნიკა',
            'customer_phone'   => '599000000',
            'delivery_method'  => 'pickup',
            'preferred_branch' => 'Saburtalo',
        ]);

        $this->assertTrue($collector->confirm($order->fresh()));
    }
}
