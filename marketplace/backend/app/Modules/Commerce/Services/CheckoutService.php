<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\Order;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * @param  array<string, mixed>  $shipping
     */
    public function place(User $user, Cart $cart, array $shipping, string $paymentMethod = 'cod'): Order
    {
        abort_if($cart->items()->count() === 0, 422, 'კალათა ცარიელია');

        return DB::transaction(function () use ($user, $cart, $shipping, $paymentMethod) {
            $cart->load('items.product');

            $subtotal = 0.0;
            foreach ($cart->items as $item) {
                $product = $item->product;
                if (! $product->is_made_to_order) {
                    abort_if($product->stock < $item->quantity, 422, "მარაგი ამოიწურა: {$product->title_ka}");
                }
                $subtotal += (float) $item->unit_price_gel * $item->quantity;
            }

            $shippingCost = (float) config('marketplace.default_shipping_gel', 10.00);
            $total = $subtotal + $shippingCost;

            $order = Order::create([
                'number' => Order::generateNumber(),
                'user_id' => $user->id,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'payment_status' => 'unpaid',
                'subtotal_gel' => $subtotal,
                'shipping_gel' => $shippingCost,
                'total_gel' => $total,
                'shipping_name' => $shipping['name'],
                'shipping_phone' => $shipping['phone'],
                'shipping_region' => $shipping['region'],
                'shipping_city' => $shipping['city'],
                'shipping_address' => $shipping['address'],
                'shipping_notes' => $shipping['notes'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $product = $item->product;
                $order->items()->create([
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'title_snapshot' => $product->title_ka,
                    'image_snapshot' => $product->coverImage()?->path,
                    'unit_price_gel' => $item->unit_price_gel,
                    'quantity' => $item->quantity,
                    'line_total_gel' => (float) $item->unit_price_gel * $item->quantity,
                ]);
                if (! $product->is_made_to_order) {
                    $product->decrement('stock', $item->quantity);
                }
            }

            $cart->items()->delete();

            return $order;
        });
    }
}
