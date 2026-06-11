@props(['seller'])

<a href="{{ route('seller.show', $seller->slug) }}" class="card p-5 block group">
    <div class="flex items-start gap-4">
        <div class="w-14 h-14 rounded-full bg-brand-cream-200 flex items-center justify-center text-brand-gold-700 font-display text-xl shrink-0">
            {{ mb_substr($seller->business_name, 0, 1) }}
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-brand-ink group-hover:text-brand-red-500 transition">{{ $seller->business_name }}</h3>
            <p class="text-xs text-brand-ink/60 mt-1">{{ config('marketplace.regions.'.$seller->region, $seller->region) }}@if ($seller->municipality), {{ $seller->municipality }}@endif</p>
            <div class="mt-2 flex flex-wrap gap-1">
                <span class="badge-georgia">{{ config('marketplace.seller_sectors.'.$seller->sector, $seller->sector) }}</span>
                @if ($seller->is_made_in_georgia_verified)
                    <span class="badge-verified">დადასტურებული</span>
                @endif
            </div>
        </div>
    </div>
    @if ($seller->story)
        <p class="mt-3 text-sm text-brand-ink/70 line-clamp-2">{{ $seller->story }}</p>
    @endif
</a>
