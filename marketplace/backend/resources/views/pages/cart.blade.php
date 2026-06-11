<x-layouts.app>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="section-title gold-rule">კალათა</h1>

        @if ($cart->items->isEmpty())
            <div class="mt-10 card p-12 text-center">
                <p class="text-brand-ink/60">თქვენი კალათა ცარიელია.</p>
                <a href="{{ route('catalog.index') }}" class="mt-4 btn-primary inline-flex">დაიწყე ყიდვა</a>
            </div>
        @else
            <div class="mt-10 grid lg:grid-cols-[1fr_360px] gap-8">
                <div class="space-y-4">
                    @foreach ($cart->items as $item)
                        <div class="card p-4 flex gap-4 items-center">
                            <div class="w-16 h-16 bg-brand-cream-100 rounded-xl flex items-center justify-center text-brand-gold-700 font-display shrink-0">
                                {{ mb_substr($item->product->title_ka, 0, 1) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('product.show', $item->product->slug) }}" class="font-medium hover:text-brand-red-500">{{ $item->product->title_ka }}</a>
                                <p class="text-xs text-brand-ink/60">{{ $item->product->seller?->business_name }}</p>
                                <p class="text-sm font-semibold mt-1">{{ number_format($item->unit_price_gel, 2) }} ₾ × {{ $item->quantity }}</p>
                            </div>
                            <form method="POST" action="{{ route('cart.remove', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-brand-red-500 text-sm hover:underline">წაშლა</button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <aside class="card p-6 h-fit">
                    <h2 class="font-display text-lg mb-4">შეჯამება</h2>
                    @php
                        $subtotal = $cart->subtotalGel();
                        $shipping = (float) config('marketplace.default_shipping_gel');
                    @endphp
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-brand-ink/60">ჯამი:</span> <strong>{{ number_format($subtotal, 2) }} ₾</strong></div>
                        <div class="flex justify-between"><span class="text-brand-ink/60">მიწოდება:</span> <strong>{{ number_format($shipping, 2) }} ₾</strong></div>
                        <div class="flex justify-between border-t border-brand-cream-200 pt-2 mt-2 text-base"><span>გადასახდელი:</span> <strong>{{ number_format($subtotal + $shipping, 2) }} ₾</strong></div>
                    </div>
                    @auth
                        <a href="{{ route('checkout.show') }}" class="btn-primary w-full mt-6">გადახდაზე გადასვლა</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary w-full mt-6">შესვლა გადახდისთვის</a>
                    @endauth
                </aside>
            </div>
        @endif
    </div>
</x-layouts.app>
