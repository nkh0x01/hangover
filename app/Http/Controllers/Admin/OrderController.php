<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Services\Sales\CheckoutCollector;
use App\Services\Sales\PaymentLinkGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrderController extends Controller
{
    public function __construct(
        private CheckoutCollector $checkout,
        private PaymentLinkGenerator $payments,
    ) {}

    public function index(Request $request)
    {
        $q = Order::query()->with('customer')->latest('id');
        if ($s = $request->input('status')) {
            $q->where('status', $s);
        }
        return response()->json([
            'data' => $q->limit(100)->get(),
        ]);
    }

    public function show(int $id)
    {
        return response()->json(Order::with(['customer', 'conversation'])->findOrFail($id));
    }

    public function confirm(int $id)
    {
        $order = Order::findOrFail($id);
        $ok = $this->checkout->confirm($order);
        return response()->json([
            'ok'      => $ok,
            'missing' => $ok ? [] : $this->checkout->missingFields($order),
        ]);
    }

    public function paymentLink(int $id)
    {
        $order = Order::with('conversation')->findOrFail($id);
        $link  = $this->payments->generate($order->id, $order->conversation);
        return response()->json(['link' => $link]);
    }

    public function cancel(int $id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => Order::STATUS_CANCELLED]);
        return response()->json(['ok' => true]);
    }
}
