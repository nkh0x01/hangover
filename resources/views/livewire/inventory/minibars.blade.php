<div>
    <x-slot name="header">{{ __('Minibars') }}</x-slot>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($rows as $row)
            @php $r = $row['room']; @endphp
            <a href="{{ route('rooms.minibar', $r) }}"
               class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:-translate-y-0.5 hover:shadow-md hover:border-slate-300 transition">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-2xl font-bold text-slate-900">{{ $r->number }}</div>
                        <div class="text-xs text-slate-500">{{ $r->roomType?->name }}</div>
                    </div>
                    @if ($row['needsRefill'] > 0)
                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">{{ __('Needs refill') }}</span>
                    @else
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">{{ __('Stocked') }}</span>
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-2 gap-x-3 text-xs text-slate-500">
                    <div><span class="block text-[10px] uppercase">{{ __('In room') }}</span><span class="text-base font-semibold text-slate-900">{{ $row['current'] }}</span></div>
                    <div><span class="block text-[10px] uppercase">{{ __('Par total') }}</span><span class="text-base font-semibold text-slate-900">{{ $row['par'] }}</span></div>
                </div>
            </a>
        @endforeach
    </div>
</div>
