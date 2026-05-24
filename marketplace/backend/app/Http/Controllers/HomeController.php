<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Cms\Models\HeroSection;
use App\Modules\Seller\Models\Seller;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.home', [
            'hero' => HeroSection::where('key', 'home_hero')->where('is_active', true)->first(),
            'categories' => Category::whereNull('parent_id')->where('is_active', true)->orderBy('position')->take(10)->get(),
            'featuredProducts' => Product::published()->with(['seller', 'images'])->latest('published_at')->take(8)->get(),
            'featuredSellers' => Seller::verified()->inRandomOrder()->take(6)->get(),
        ]);
    }
}
