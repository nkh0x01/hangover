<div>
    <x-slot name="header">Dashboard</x-slot>

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
        <x-kpi-tile label="Arrivals today"    :value="$arrivalsToday->count()" tone="indigo" icon="→" />
        <x-kpi-tile label="Departures today"  :value="$departuresToday->count()" tone="amber" icon="←" />
        <x-kpi-tile label="Occupied rooms"    :value="$occupied" tone="rose" icon="🛏" />
        <x-kpi-tile label="Available rooms"   :value="$available" tone="emerald" icon="✓" />
        <x-kpi-tile label="Dirty rooms"       :value="$dirty" tone="slate" icon="✱" />
        <x-kpi-tile label="Today revenue"     :value="number_format($revenueToday, 2).' '.($property?->base_currency ?? '')" tone="violet" icon="$" />
        <x-kpi-tile label="Unpaid reservations" :value="$unpaid" tone="red" icon="!" />
    </div>

    {{-- Quick actions --}}
    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('reservations.create') }}"
           class="rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
            + New reservation
        </a>
        <a href="{{ route('calendar') }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Open calendar
        </a>
        <a href="{{ route('rooms.index') }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Rooms
        </a>
    </div>

    {{-- Arrivals / Departures --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white">
            <header class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">
                Arrivals today
            </header>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($arrivalsToday as $r)
                    <li class="flex items-center justify-between px-5 py-3">
                        <div>
                            <a href="{{ route('reservations.show', $r) }}" class="font-medium text-slate-900 hover:underline">
                                {{ $r->leadGuest?->full_name ?? '—' }}
                            </a>
                            <div class="text-xs text-slate-500">
                                Room {{ $r->room?->number ?? '—' }} · {{ $r->nights }} night{{ $r->nights === 1 ? '' : 's' }} · {{ $r->code }}
                            </div>
                        </div>
                        <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">{{ $r->status }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-400">No arrivals today.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white">
            <header class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">
                Departures today
            </header>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($departuresToday as $r)
                    <li class="flex items-center justify-between px-5 py-3">
                        <div>
                            <a href="{{ route('reservations.show', $r) }}" class="font-medium text-slate-900 hover:underline">
                                {{ $r->leadGuest?->full_name ?? '—' }}
                            </a>
                            <div class="text-xs text-slate-500">
                                Room {{ $r->room?->number ?? '—' }} · {{ $r->payment_status }}
                            </div>
                        </div>
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">{{ $r->status }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-400">No departures today.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
