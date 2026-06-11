<x-layouts.app>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
        <nav class="text-sm text-brand-ink/60 mb-6">
            <a href="{{ route('home') }}" class="hover:text-brand-red-500">მთავარი</a>
            <span class="mx-1">/</span>
            <a href="{{ route('catalog.index') }}" class="hover:text-brand-red-500">კატალოგი</a>
            <span class="mx-1">/</span>
            <a href="{{ route('catalog.category', $product->category->slug) }}" class="hover:text-brand-red-500">{{ $product->category->name_ka }}</a>
        </nav>

        <div class="grid lg:grid-cols-2 gap-12">
            {{-- Gallery --}}
            <div>
                <div class="aspect-square card bg-brand-cream-50 flex items-center justify-center">
                    <span class="font-display text-8xl text-brand-gold-700 opacity-30">{{ mb_substr($product->title_ka, 0, 1) }}</span>
                </div>
            </div>

            {{-- Info --}}
            <div>
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="badge-georgia">{{ __('badge.made_in_georgia') }}</span>
                    @if ($product->production_type === 'handmade')
                        <span class="badge-handmade">ხელნაკეთი</span>
                    @endif
                    @if ($product->seller?->is_made_in_georgia_verified)
                        <span class="badge-verified">დადასტურებული მეწარმე</span>
                    @endif
                </div>

                <h1 class="font-display text-3xl text-brand-ink">{{ $product->title_ka }}</h1>

                <a href="{{ route('seller.show', $product->seller->slug) }}" class="mt-2 text-sm text-brand-ink/60 hover:text-brand-red-500 inline-block">
                    {{ $product->seller->business_name }} • {{ config('marketplace.regions.'.$product->seller->region, $product->seller->region) }}
                </a>

                <div class="mt-6 flex items-baseline gap-3">
                    <span class="font-display text-4xl text-brand-red-500">{{ number_format($product->price_gel, 2) }} ₾</span>
                    @if ($product->compare_at_price_gel && $product->compare_at_price_gel > $product->price_gel)
                        <span class="text-lg text-brand-ink/40 line-through">{{ number_format($product->compare_at_price_gel, 0) }} ₾</span>
                    @endif
                </div>

                <div class="mt-6 prose prose-sm max-w-none text-brand-ink/80">
                    {{ $product->description_ka }}
                </div>

                <div class="mt-6 space-y-2 text-sm">
                    <div class="flex gap-2"><span class="text-brand-ink/60 w-32">წარმოების ტიპი:</span> <span>{{ config('marketplace.production_types.'.$product->production_type, $product->production_type) }}</span></div>
                    @if ($product->is_made_to_order)
                        <div class="flex gap-2"><span class="text-brand-ink/60 w-32">წარმოების დრო:</span> <span>{{ $product->lead_time_days }} დღე</span></div>
                    @else
                        <div class="flex gap-2"><span class="text-brand-ink/60 w-32">მარაგი:</span> <span>{{ $product->stock }} ცალი</span></div>
                    @endif
                    @if ($product->materials)
                        <div class="flex gap-2"><span class="text-brand-ink/60 w-32">მასალები:</span> <span>{{ implode(', ', $product->materials) }}</span></div>
                    @endif
                </div>

                <form method="POST" action="{{ route('cart.add') }}" class="mt-8 flex flex-col sm:flex-row gap-3">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="number" name="quantity" value="1" min="1" max="{{ $product->is_made_to_order ? 10 : $product->stock }}" class="rounded-full border-brand-cream-200 w-24 text-center">
                    <button type="submit" class="btn-primary flex-1">კალათაში დამატება</button>
                </form>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-20">
                <h2 class="section-title gold-rule">მსგავსი პროდუქტები</h2>
                <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-6">
                    @foreach ($related as $p)
                        <x-product-card :product="$p"/>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
