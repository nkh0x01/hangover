<?php

declare(strict_types=1);

namespace App\Modules\Seller\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class SellerProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $seller = $request->user()->seller()->firstOrFail();
        $products = $seller->products()->with(['category', 'images'])->latest()->paginate(24);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $seller = $request->user()->seller()->firstOrFail();
        abort_unless($seller->isVerified(), 403, 'მაღაზია ჯერ არ არის დადასტურებული');

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title_ka' => ['required', 'string', 'max:160'],
            'title_en' => ['nullable', 'string', 'max:160'],
            'description_ka' => ['required', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'materials' => ['nullable', 'array'],
            'price_gel' => ['required', 'numeric', 'min:0'],
            'compare_at_price_gel' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_made_to_order' => ['nullable', 'boolean'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'production_type' => ['nullable', 'string', 'max:32'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['seller_id'] = $seller->id;
        $data['slug'] = Str::slug($data['title_ka']).'-'.Str::random(6);
        $data['status'] = 'pending';

        $product = Product::create($data);

        return response()->json([
            'data' => $product,
            'message_ka' => 'პროდუქტი წარმატებით დაემატა, ელოდება მოდერაციას',
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $seller = $request->user()->seller()->firstOrFail();
        abort_unless($product->seller_id === $seller->id, 403);

        $data = $request->validate([
            'title_ka' => ['sometimes', 'string', 'max:160'],
            'description_ka' => ['sometimes', 'string', 'max:5000'],
            'price_gel' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'is_made_to_order' => ['sometimes', 'boolean'],
        ]);

        $product->update($data);

        return response()->json(['data' => $product->fresh()]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $seller = $request->user()->seller()->firstOrFail();
        abort_unless($product->seller_id === $seller->id, 403);

        $product->delete();

        return response()->json(['ok' => true]);
    }
}
