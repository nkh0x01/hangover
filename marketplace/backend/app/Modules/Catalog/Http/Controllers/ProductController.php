<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Product::query()->published()->with(['seller', 'category', 'images']);

        if ($request->filled('category')) {
            $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category')));
        }
        if ($request->filled('seller')) {
            $q->whereHas('seller', fn ($s) => $s->where('slug', $request->string('seller')));
        }
        if ($request->filled('region')) {
            $q->whereHas('seller', fn ($s) => $s->where('region', $request->string('region')));
        }
        if ($request->filled('production_type')) {
            $q->where('production_type', $request->string('production_type'));
        }
        if ($request->filled('min_price')) {
            $q->where('price_gel', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $q->where('price_gel', '<=', (float) $request->input('max_price'));
        }
        if ($request->filled('q')) {
            $term = (string) $request->string('q');
            $q->where(function ($q) use ($term) {
                $q->where('title_ka', 'like', "%{$term}%")
                    ->orWhere('description_ka', 'like', "%{$term}%");
            });
        }

        $sort = (string) $request->string('sort', 'newest');
        match ($sort) {
            'price_asc' => $q->orderBy('price_gel'),
            'price_desc' => $q->orderByDesc('price_gel'),
            'rating' => $q->orderByDesc('rating_avg'),
            default => $q->orderByDesc('published_at'),
        };

        $products = $q->paginate((int) $request->integer('per_page', 24));

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->status === 'published', 404);

        $product->load(['seller', 'category', 'images', 'reviews' => fn ($q) => $q->approved()->latest()->limit(10)]);
        $product->increment('views_count');

        return response()->json(['data' => $product]);
    }

    public function related(Product $product): JsonResponse
    {
        $related = Product::query()
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['seller', 'images'])
            ->limit(8)
            ->get();

        return response()->json(['data' => $related]);
    }
}
