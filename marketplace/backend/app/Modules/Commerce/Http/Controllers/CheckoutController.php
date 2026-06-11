<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Commerce\Services\CartService;
use App\Modules\Commerce\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'region' => ['required', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', Rule::in(['cod', 'card_placeholder', 'bank_transfer'])],
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

        return response()->json([
            'data' => $order->load('items'),
            'message_ka' => 'შეკვეთა მიღებულია',
        ], 201);
    }
}
