<div>
    <x-slot name="header">{{ __('Point of sale') }}</x-slot>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Product grid --}}
        <section class="lg:col-span-2 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" wire:model.live.debounce.200ms="search"
                       placeholder="{{ __('Search by name, SKU, barcode…') }}" autofocus
                       class="w-72 rounded-md border-slate-300 text-sm">
                <select wire:model.live="locationId" class="rounded-md border-slate-300 text-sm">
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @forelse ($products as $p)
                    <button type="button" wire:click="addToCart({{ $p->id }})"
                            class="text-left rounded-lg border border-slate-200 bg-white p-3 hover:-translate-y-0.5 hover:shadow-md hover:border-slate-300 transition">
                        <div class="font-medium text-slate-900 leading-tight">{{ $p->name }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ $p->sku ?? '' }}</div>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-sm font-semibold">{{ number_format((float) $p->sale_price, 2) }} {{ $currency }}</span>
                            <span class="text-[10px] uppercase {{ ($p->location_stock ?? 0) <= 0 ? 'text-red-600 font-bold' : 'text-slate-500' }}">
                                {{ __('stock') }}: {{ $p->location_stock ?? 0 }}
                            </span>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-slate-400 text-sm">
                        {{ __('No matching products.') }}
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Cart --}}
        <aside class="space-y-3">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 flex items-center justify-between">
                    <span>{{ __('Cart') }}</span>
                    @if (! empty($cart))
                        <button wire:click="clear" class="text-xs text-slate-400 hover:text-slate-700">{{ __('Clear') }}</button>
                    @endif
                </header>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($cartLines as $line)
                        <li class="px-4 py-2 flex items-center justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-slate-900 truncate">{{ $line['product']?->name }}</div>
                                <div class="text-xs text-slate-500">{{ number_format($line['unit'], 2) }} {{ $currency }} × {{ $line['quantity'] }}</div>
                            </div>
                            <input type="number" min="0" value="{{ $line['quantity'] }}"
                                   wire:change="setQty({{ $line['product']->id }}, parseInt($event.target.value))"
                                   class="w-14 rounded-md border-slate-300 text-right text-sm">
                            <button wire:click="remove({{ $line['product']->id }})" class="text-slate-300 hover:text-red-600">✕</button>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-slate-400 text-sm">{{ __('Cart is empty.') }}</li>
                    @endforelse
                </ul>
                <footer class="border-t border-slate-100 px-4 py-3 space-y-2">
                    <div class="flex items-center justify-between font-semibold text-slate-900">
                        <span>{{ __('Total') }}</span>
                        <span>{{ number_format($total, 2) }} {{ $currency }}</span>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 uppercase">{{ __('Method') }}</label>
                        <select wire:model="paymentMethod" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            @foreach ($methods as $m)
                                <option value="{{ $m }}">{{ __(str_replace('_', ' ', $m)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button wire:click="checkout"
                            wire:loading.attr="disabled" wire:target="checkout"
                            @disabled(empty($cart))
                            class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                        <x-spinner wire:loading wire:target="checkout" class="h-4 w-4 -ml-1" />
                        {{ __('Complete sale') }}
                    </button>
                </footer>
            </div>

            @if ($lastInvoiceNumber)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                    <div class="font-medium text-emerald-900">{{ __('Last sale: :n', ['n' => $lastInvoiceNumber]) }}</div>
                    <a href="{{ route('invoices.show', $lastInvoiceId) }}" class="mt-1 inline-block text-xs text-emerald-700 underline">{{ __('View receipt') }} →</a>
                </div>
            @endif
        </aside>
    </div>
</div>
