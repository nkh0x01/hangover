<x-layouts.app>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="section-title gold-rule">გადახდა</h1>

        <form method="POST" action="{{ route('checkout.place') }}" class="mt-10 grid lg:grid-cols-[1fr_360px] gap-8">
            @csrf

            <div class="card p-6 space-y-4">
                <h2 class="font-display text-lg">მიწოდების ინფორმაცია</h2>

                <div>
                    <label class="text-sm font-medium">სახელი და გვარი *</label>
                    <input type="text" name="name" required value="{{ old('name', auth()->user()->name) }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">ტელეფონი *</label>
                    <input type="text" name="phone" required value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">რეგიონი *</label>
                        <select name="region" required class="mt-1 w-full rounded-lg border-brand-cream-200">
                            @foreach (config('marketplace.regions') as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">ქალაქი *</label>
                        <input type="text" name="city" required value="{{ old('city') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium">მისამართი *</label>
                    <input type="text" name="address" required value="{{ old('address') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">დამატებითი კომენტარი</label>
                    <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border-brand-cream-200">{{ old('notes') }}</textarea>
                </div>

                <h2 class="font-display text-lg mt-6">გადახდის მეთოდი</h2>
                <label class="flex items-start gap-3 p-4 border border-brand-cream-200 rounded-xl cursor-pointer hover:bg-brand-cream-50">
                    <input type="radio" name="payment_method" value="cod" checked class="mt-1 text-brand-red-500">
                    <div>
                        <p class="font-medium">გადახდა მიწოდებისას</p>
                        <p class="text-xs text-brand-ink/60">გადაიხდი ნაღდი ფულით, როცა შეკვეთა მიგიტანენ.</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 p-4 border border-brand-cream-200 rounded-xl opacity-50 cursor-not-allowed">
                    <input type="radio" disabled class="mt-1">
                    <div>
                        <p class="font-medium">ბარათით გადახდა (მალე)</p>
                        <p class="text-xs text-brand-ink/60">Bank of Georgia და TBC ინტეგრაცია მზადდება.</p>
                    </div>
                </label>
            </div>

            <aside class="card p-6 h-fit">
                <h2 class="font-display text-lg mb-4">შენი შეკვეთა</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ($cart->items as $item)
                        <li class="flex justify-between gap-2">
                            <span class="truncate">{{ $item->product->title_ka }} × {{ $item->quantity }}</span>
                            <span class="shrink-0">{{ number_format((float) $item->unit_price_gel * $item->quantity, 2) }} ₾</span>
                        </li>
                    @endforeach
                </ul>
                @php
                    $subtotal = $cart->subtotalGel();
                    $shipping = (float) config('marketplace.default_shipping_gel');
                @endphp
                <div class="mt-4 pt-4 border-t border-brand-cream-200 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-brand-ink/60">ჯამი:</span> <strong>{{ number_format($subtotal, 2) }} ₾</strong></div>
                    <div class="flex justify-between"><span class="text-brand-ink/60">მიწოდება:</span> <strong>{{ number_format($shipping, 2) }} ₾</strong></div>
                    <div class="flex justify-between text-base"><span>გადასახდელი:</span> <strong>{{ number_format($subtotal + $shipping, 2) }} ₾</strong></div>
                </div>
                <button type="submit" class="btn-primary w-full mt-6">დაადასტურე შეკვეთა</button>
            </aside>
        </form>
    </div>
</x-layouts.app>
