<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CartItem;
use Illuminate\Http\Request;

class CartService
{
    public function resolve(Request $request): Cart
    {
        $user = $request->user();
        if ($user) {
            return Cart::firstOrCreate(['user_id' => $user->id], ['currency' => 'GEL']);
        }

        $sessionId = $request->cookie('mp_cart') ?: bin2hex(random_bytes(16));
        $cart = Cart::firstOrCreate(['session_id' => $sessionId], ['currency' => 'GEL']);
        cookie()->queue('mp_cart', $sessionId, 60 * 24 * 30);

        return $cart;
    }

    public function addItem(Cart $cart, Product $product, int $quantity = 1): CartItem
    {
        abort_unless($product->status === 'published', 422, 'პროდუქტი მიუწვდომელია');
        abort_if($product->stock < $quantity && ! $product->is_made_to_order, 422, 'მარაგი არ არის საკმარისი');

        $item = $cart->items()->where('product_id', $product->id)->first();
        if ($item) {
            $item->quantity += $quantity;
            $item->save();

            return $item;
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price_gel' => $product->price_gel,
        ]);
    }

    public function updateItem(CartItem $item, int $quantity): CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return $item;
        }
        $item->quantity = $quantity;
        $item->save();

        return $item;
    }
}
