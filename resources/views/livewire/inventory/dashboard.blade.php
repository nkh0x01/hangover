<div>
    <x-slot name="header">{{ __('Inventory') }}</x-slot>

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi-tile :label="__('Products')"        :value="$totalProducts" tone="indigo" icon="📦" />
        <x-kpi-tile :label="__('Locations')"       :value="$totalLocations" tone="violet" icon="📍" />
        <x-kpi-tile :label="__('Minibars')"        :value="$minibarCount" tone="amber" icon="🍫" />
        <x-kpi-tile :label="__('Stock value')"     :value="number_format($totalStockValue, 2).' '.($property?->base_currency ?? '')" tone="emerald" icon="$" />
    </div>

    {{-- Quick actions --}}
    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('products.index') }}"   class="rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">{{ __('Manage products') }}</a>
        <a href="{{ route('inventory.pos') }}"    class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('Open POS') }}</a>
        <a href="{{ route('inventory.movements') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('Movements ledger') }}</a>
        <a href="{{ route('inventory.minibars') }}"  class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('Minibars') }}</a>
        <a href="{{ route('inventory.locations') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('Locations') }}</a>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        {{-- Low stock --}}
        <section class="rounded-xl border border-slate-200 bg-white">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">
                <span>{{ __('Low stock alerts') }}</span>
                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 ring-1 ring-red-200">{{ $lowStock->count() }}</span>
            </header>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($lowStock as $row)
                    <li class="flex items-center justify-between px-5 py-2.5">
                        <div class="font-medium">{{ $row['product']->name }}</div>
                        <div class="text-xs text-slate-500">
                            <span class="text-red-600 font-medium">{{ $row['total'] }}</span> / {{ __('threshold') }} {{ $row['product']->low_stock_threshold }}
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-400">{{ __('All products above threshold.') }}</li>
                @endforelse
            </ul>
        </section>

        {{-- Recent movements --}}
        <section class="rounded-xl border border-slate-200 bg-white">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">
                <span>{{ __('Recent movements') }}</span>
                <a href="{{ route('inventory.movements') }}" class="text-xs text-slate-500 hover:text-slate-700">{{ __('See all') }} →</a>
            </header>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recentMovements as $m)
                    <li class="px-5 py-2.5">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ $m->product?->name }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ __($m->type) }}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            {{ $m->fromLocation?->name ?? '—' }} → {{ $m->toLocation?->name ?? '—' }} · {{ $m->quantity }} · {{ optional($m->occurred_at)->format('Y-m-d H:i') }}
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-400">{{ __('No movements yet.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
