<div>
    <x-slot name="header">{{ __('Dashboard') }}</x-slot>

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
        <x-kpi-tile :label="__('Arrivals today')"    :value="$arrivalsToday->count()" tone="indigo" icon="→" />
        <x-kpi-tile :label="__('Departures today')"  :value="$departuresToday->count()" tone="amber" icon="←" />
        <x-kpi-tile :label="__('Occupied rooms')"    :value="$occupied" tone="rose" icon="🛏" />
        <x-kpi-tile :label="__('Available rooms')"   :value="$available" tone="emerald" icon="✓" />
        <x-kpi-tile :label="__('Dirty rooms')"       :value="$dirty" tone="slate" icon="✱" />
        <x-kpi-tile :label="__('Today revenue')"     :value="number_format($revenueToday, 2).' '.($property?->base_currency ?? '')" tone="violet" icon="$" />
        <x-kpi-tile :label="__('Unpaid reservations')" :value="$unpaid" tone="red" icon="!" />
    </div>

    {{-- Quick actions --}}
    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('reservations.create') }}"
           class="rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
            {{ __('+ New reservation') }}
        </a>
        <a href="{{ route('calendar') }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            {{ __('Open calendar') }}
        </a>
        <a href="{{ route('rooms.index') }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            {{ __('Rooms') }}
        </a>
    </div>

    {{-- Arrivals / Departures --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white">
            <header class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">
                {{ __('Arrivals today') }}
            </header>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($arrivalsToday as $r)
                    <li class="flex items-center justify-between px-5 py-3">
                        <div>
                            <a href="{{ route('reservations.show', $r) }}" class="font-medium text-slate-900 hover:underline">
                                {{ $r->leadGuest?->full_name ?? '—' }}
                            </a>
                            <div class="text-xs text-slate-500">
                                {{ __('Room') }} {{ $r->room?->number ?? '—' }} · {{ trans_choice(':count night|:count nights', $r->nights, ['count' => $r->nights]) }} · {{ $r->code }}
                            </div>
                        </div>
                        <x-status-pill :value="$r->status" />
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-400">{{ __('No arrivals today.') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white">
            <header class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">
                {{ __('Departures today') }}
            </header>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($departuresToday as $r)
                    <li class="flex items-center justify-between px-5 py-3">
                        <div>
                            <a href="{{ route('reservations.show', $r) }}" class="font-medium text-slate-900 hover:underline">
                                {{ $r->leadGuest?->full_name ?? '—' }}
                            </a>
                            <div class="text-xs text-slate-500">
                                {{ __('Room') }} {{ $r->room?->number ?? '—' }} · {{ __(str_replace('_', ' ', $r->payment_status)) }}
                            </div>
                        </div>
                        <x-status-pill :value="$r->status" />
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-400">{{ __('No departures today.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
