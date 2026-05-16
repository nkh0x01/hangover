<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\Gadget\Mappers\OrderMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_a_valid_woocommerce_order_payload_for_pickup(): void
    {
        Product::create([
            'sku' => 'P1', 'source_id' => '999', 'name' => 'Test', 'category' => 'cases',
            'price' => 50, 'stock_total' => 10, 'is_active' => true,
        ]);
        $customer = Customer::create(['platform' => 'whatsapp', 'platform_user_id' => '995599100100']);
        $order = Order::create([
            'customer_id'      => $customer->id,
            'customer_name'    => 'Nika Beridze',
            'customer_phone'   => '599100100',
            'preferred_branch' => 'Saburtalo',
            'delivery_method'  => 'pickup',
            'payment_method'   => 'branch',
            'items_json'       => [['sku' => 'P1', 'qty' => 2, 'price' => 50]],
            'subtotal'         => 100,
            'total'            => 100,
            'currency'         => 'GEL',
        ]);

        $payload = (new OrderMapper())->toWoo($order);

        $this->assertSame('cod', $payload['payment_method']);
        $this->assertSame('GEL', $payload['currency']);
        $this->assertSame('Nika', $payload['billing']['first_name']);
        $this->assertSame('Beridze', $payload['billing']['last_name']);
        $this->assertCount(1, $payload['line_items']);
        $this->assertSame(999, $payload['line_items'][0]['product_id']);
        $this->assertSame(2, $payload['line_items'][0]['quantity']);
        $this->assertSame('local_pickup', $payload['shipping_lines'][0]['method_id']);
        $this->assertSame('0.00', $payload['shipping_lines'][0]['total']);
    }

    public function test_courier_adds_flat_rate_shipping(): void
    {
        Product::create(['sku' => 'P2', 'source_id' => '1', 'name' => 'X', 'category' => 'c', 'price' => 10, 'stock_total' => 1]);
        $customer = Customer::create(['platform' => 'whatsapp', 'platform_user_id' => 'x']);
        $order = Order::create([
            'customer_id'      => $customer->id,
            'customer_name'    => 'A B',
            'customer_phone'   => '5',
            'delivery_method'  => 'courier',
            'payment_method'   => 'cod',
            'delivery_fee'     => 10,
            'items_json'       => [['sku' => 'P2', 'qty' => 1, 'price' => 10]],
            'total'            => 20,
            'currency'         => 'GEL',
        ]);

        $payload = (new OrderMapper())->toWoo($order);
        $this->assertSame('flat_rate', $payload['shipping_lines'][0]['method_id']);
        $this->assertSame('10.00', $payload['shipping_lines'][0]['total']);
    }
}
