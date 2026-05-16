<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\Gadget\GadgetApi;
use App\Services\Gadget\OrderPush;
use App\Services\Gadget\WooCommerceClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_pushes_a_confirmed_order_once_and_stores_external_id(): void
    {
        config()->set('gadget.consumer_key',    'ck_test');
        config()->set('gadget.consumer_secret', 'cs_test');

        // Mock the WC responses: empty customer search → create customer → create order.
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),                                 // customers?search=...
            new Response(201, [], json_encode(['id' => 555, 'email' => 'x'])),     // customers POST (create)
            new Response(201, [], json_encode(['id' => 8888, 'status' => 'pending', 'order_key' => 'wc_order_k1'])), // orders POST
        ]);
        $this->bindMockedWooClient($mock);

        [$order] = $this->seedOrder();

        $result = app(OrderPush::class)->push($order->fresh());

        $this->assertTrue($result['ok']);
        $this->assertSame('8888', $result['external_order_id']);
        $this->assertSame('8888', $order->fresh()->external_order_id);
    }

    public function test_does_not_double_push_when_external_order_id_already_set(): void
    {
        config()->set('gadget.consumer_key',    'ck_test');
        config()->set('gadget.consumer_secret', 'cs_test');

        $mock = new MockHandler([]); // strict — any HTTP call would fail because the queue is empty.
        $this->bindMockedWooClient($mock);

        [$order] = $this->seedOrder();
        $order->update(['external_order_id' => '999']);

        $result = app(OrderPush::class)->push($order->fresh());

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['reused'] ?? false);
        $this->assertSame('999', $result['external_order_id']);
    }

    private function bindMockedWooClient(MockHandler $mock): void
    {
        $stack = HandlerStack::create($mock);
        $http  = new Client(['handler' => $stack, 'http_errors' => false]);

        // Replace WooCommerceClient's internal Guzzle via subclass-on-the-fly.
        $this->app->singleton(WooCommerceClient::class, function () use ($http) {
            $c = new class($http) extends WooCommerceClient {
                public function __construct(\GuzzleHttp\Client $injected)
                {
                    parent::__construct([
                        'base_url'        => 'https://gadget.ge',
                        'api_path'        => '/wp-json/wc/v3',
                        'consumer_key'    => 'ck_test',
                        'consumer_secret' => 'cs_test',
                        'timeout'         => 5,
                        'retries'         => 0,
                        'verify_tls'      => false,
                        'sync'            => ['page_size' => 10],
                    ]);
                    $rc = new \ReflectionClass($this);
                    $prop = $rc->getProperty('http');
                    $prop->setValue($this, $injected);
                }
            };
            return $c;
        });
        $this->app->singleton(GadgetApi::class, fn ($app) => new GadgetApi($app->make(WooCommerceClient::class)));
    }

    private function seedOrder(): array
    {
        $p = Product::create([
            'sku' => 'P1', 'source_id' => '999', 'name' => 'Test', 'category' => 'cases',
            'price' => 50, 'stock_total' => 10, 'is_active' => true,
        ]);
        $customer = Customer::create(['platform' => 'whatsapp', 'platform_user_id' => '995599100100', 'phone' => '599100100']);
        $order = Order::create([
            'customer_id'      => $customer->id,
            'customer_name'    => 'Nika Beridze',
            'customer_phone'   => '599100100',
            'preferred_branch' => 'Saburtalo',
            'delivery_method'  => 'pickup',
            'payment_method'   => 'branch',
            'items_json'       => [['sku' => 'P1', 'qty' => 1, 'price' => 50]],
            'subtotal'         => 50,
            'total'            => 50,
            'currency'         => 'GEL',
            'status'           => Order::STATUS_CONFIRMED,
        ]);
        return [$order, $customer, $p];
    }
}
