<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogWebController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.catalog', [
            'pageTitle' => 'პროდუქტების კატალოგი',
            'products' => $this->buildQuery($request)->paginate(24)->withQueryString(),
            'categories' => Category::whereNull('parent_id')->where('is_active', true)->orderBy('position')->get(),
        ]);
    }

    public function category(Request $request, Category $category): View
    {
        $request->merge(['category' => $category->slug]);

        return view('pages.catalog', [
            'pageTitle' => $category->name_ka,
            'categoryDesc' => null,
            'products' => $this->buildQuery($request)->paginate(24)->withQueryString(),
            'categories' => Category::whereNull('parent_id')->where('is_active', true)->orderBy('position')->get(),
        ]);
    }

    public function product(Product $product): View
    {
        abort_unless($product->status === 'published', 404);
        $product->load(['seller', 'category', 'images']);
        $product->increment('views_count');

        return view('pages.product-show', [
            'product' => $product,
            'related' => Product::published()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->with(['seller', 'images'])
                ->limit(4)
                ->get(),
        ]);
    }

    private function buildQuery(Request $request)
    {
        $q = Product::query()->published()->with(['seller', 'images']);

        if ($request->filled('category')) {
            $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category')));
        }
        if ($request->filled('region')) {
            $q->whereHas('seller', fn ($s) => $s->where('region', $request->string('region')));
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

        match ((string) $request->string('sort', 'newest')) {
            'price_asc' => $q->orderBy('price_gel'),
            'price_desc' => $q->orderByDesc('price_gel'),
            'rating' => $q->orderByDesc('rating_avg'),
            default => $q->orderByDesc('published_at'),
        };

        return $q;
    }
}
