<x-layouts.app>
    <div class="bg-brand-cream-50 border-b border-brand-cream-200">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
            <div class="flex flex-col md:flex-row gap-6 items-start">
                <div class="w-20 h-20 rounded-full bg-brand-cream-200 flex items-center justify-center text-brand-gold-700 font-display text-3xl shrink-0">
                    {{ mb_substr($seller->business_name, 0, 1) }}
                </div>
                <div class="flex-1">
                    <div class="flex flex-wrap gap-2 mb-2">
                        @if ($seller->is_made_in_georgia_verified)
                            <span class="badge-verified">დადასტურებული მეწარმე</span>
                        @endif
                        <span class="badge-georgia">{{ config('marketplace.seller_sectors.'.$seller->sector, $seller->sector) }}</span>
                    </div>
                    <h1 class="section-title">{{ $seller->business_name }}</h1>
                    <p class="mt-1 text-brand-ink/60">{{ config('marketplace.regions.'.$seller->region, $seller->region) }}@if ($seller->municipality), {{ $seller->municipality }}@endif</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 grid lg:grid-cols-[300px_1fr] gap-10">
        <aside>
            @if ($seller->story)
                <section class="card p-6">
                    <h2 class="font-display text-lg mb-3">მეწარმის ისტორია</h2>
                    <p class="text-sm text-brand-ink/70 whitespace-pre-line">{{ $seller->story }}</p>
                </section>
            @endif
            <section class="card p-6 mt-4 space-y-2 text-sm">
                <h3 class="font-semibold mb-2">დეტალები</h3>
                <p><span class="text-brand-ink/60">სამართლებრივი ფორმა:</span> {{ config('marketplace.legal_forms.'.$seller->legal_form, $seller->legal_form) }}</p>
                @if ($seller->website_url)
                    <p><a href="{{ $seller->website_url }}" class="text-brand-red-500 hover:underline" target="_blank" rel="noopener">ვებსაიტი ↗</a></p>
                @endif
                @if ($seller->facebook_url)
                    <p><a href="{{ $seller->facebook_url }}" class="text-brand-red-500 hover:underline" target="_blank" rel="noopener">Facebook ↗</a></p>
                @endif
                @if ($seller->instagram_url)
                    <p><a href="{{ $seller->instagram_url }}" class="text-brand-red-500 hover:underline" target="_blank" rel="noopener">Instagram ↗</a></p>
                @endif
            </section>
        </aside>

        <div>
            <h2 class="section-title gold-rule">პროდუქცია</h2>
            @if ($products->count())
                <div class="mt-8 grid grid-cols-2 md:grid-cols-3 gap-6">
                    @foreach ($products as $p)
                        <x-product-card :product="$p"/>
                    @endforeach
                </div>
                <div class="mt-10">{{ $products->links() }}</div>
            @else
                <p class="mt-8 text-brand-ink/60">ჯერ პროდუქცია არ დაგვემატებია.</p>
            @endif
        </div>
    </div>
</x-layouts.app>
