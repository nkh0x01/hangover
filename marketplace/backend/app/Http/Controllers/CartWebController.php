<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\CartService;
use App\Modules\Commerce\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartWebController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
    ) {}

    public function show(Request $request): View
    {
        $cart = $this->cartService->resolve($request)->load('items.product.seller');

        return view('pages.cart', ['cart' => $cart]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $cart = $this->cartService->resolve($request);
        $product = Product::findOrFail($data['product_id']);
        $this->cartService->addItem($cart, $product, (int) $data['quantity']);

        return back()->with('status', 'პროდუქტი წარმატებით დაემატა კალათაში');
    }

    public function remove(Request $request, CartItem $item): RedirectResponse
    {
        $cart = $this->cartService->resolve($request);
        abort_unless($item->cart_id === $cart->id, 403);
        $item->delete();

        return back();
    }

    public function checkout(Request $request): View
    {
        $cart = $this->cartService->resolve($request)->load('items.product');
        abort_if($cart->items->isEmpty(), 422, 'კალათა ცარიელია');

        return view('pages.checkout', ['cart' => $cart]);
    }

    public function placeOrder(Request $request): View
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'region' => ['required', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $cart = $this->cartService->resolve($request);
        $order = $this->checkoutService->place(
            user: $request->user(),
            cart: $cart,
            shipping: [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'region' => $data['region'],
                'city' => $data['city'],
                'address' => $data['address'],
                'notes' => $data['notes'] ?? null,
            ],
            paymentMethod: $data['payment_method'] ?? 'cod',
        );

        return view('pages.order-confirmation', ['order' => $order->load('items')]);
    }

    public function orders(Request $request): View
    {
        return view('pages.account-orders', [
            'orders' => Order::where('user_id', $request->user()->id)->latest('placed_at')->paginate(20),
        ]);
    }
}
