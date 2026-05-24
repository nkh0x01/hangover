@props(['product'])

<a href="{{ route('product.show', $product->slug) }}" class="card group block overflow-hidden">
    <div class="aspect-square bg-brand-cream-100 flex items-center justify-center text-brand-gold relative">
        @if ($product->images->first())
            <div class="w-full h-full bg-gradient-to-br from-brand-cream-200 to-brand-cream-50 flex items-center justify-center">
                <span class="font-display text-4xl text-brand-gold-700 opacity-40 px-4 text-center">{{ mb_substr($product->title_ka, 0, 1) }}</span>
            </div>
        @else
            <span class="font-display text-4xl text-brand-gold-700 opacity-40">{{ mb_substr($product->title_ka, 0, 1) }}</span>
        @endif

        <div class="absolute top-3 left-3 flex flex-col gap-1">
            <span class="badge-georgia">{{ __('badge.made_in_georgia') }}</span>
            @if ($product->production_type === 'handmade')
                <span class="badge-handmade">ხელნაკეთი</span>
            @elseif ($product->production_type === 'organic')
                <span class="badge-verified">ბიო</span>
            @endif
        </div>
    </div>

    <div class="p-4">
        <h3 class="font-semibold text-brand-ink text-base line-clamp-2 min-h-12 group-hover:text-brand-red-500 transition">{{ $product->title_ka }}</h3>
        <p class="text-xs text-brand-ink/60 mt-1">{{ $product->seller?->business_name ?? '—' }} • {{ config('marketplace.regions.'.($product->seller?->region ?? 'tbilisi'), '') }}</p>

        <div class="mt-3 flex items-center justify-between">
            <span class="font-display text-lg text-brand-ink">{{ number_format($product->price_gel, 0) }} ₾</span>
            @if ((float) $product->rating_avg > 0)
                <span class="text-xs text-brand-gold-700">★ {{ number_format($product->rating_avg, 1) }}</span>
            @endif
        </div>
    </div>
</a>
