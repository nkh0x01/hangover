<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolve($request)->load('items.product.images');

        return response()->json([
            'data' => $cart,
            'subtotal_gel' => $cart->subtotalGel(),
        ]);
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $cart = $this->cartService->resolve($request);
        $product = Product::findOrFail($data['product_id']);
        $item = $this->cartService->addItem($cart, $product, (int) $data['quantity']);

        return response()->json([
            'data' => $item->load('product'),
            'message_ka' => 'პროდუქტი დაემატა კალათაში',
        ], 201);
    }

    public function update(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->cartService->resolve($request);
        abort_unless($item->cart_id === $cart->id, 403);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:100']]);
        $this->cartService->updateItem($item, (int) $data['quantity']);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->cartService->resolve($request);
        abort_unless($item->cart_id === $cart->id, 403);
        $item->delete();

        return response()->json(['ok' => true]);
    }
}
