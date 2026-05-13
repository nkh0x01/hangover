<div>
    <x-slot name="header">{{ __('Restrictions') }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <button wire:click="shift(-7)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Week') }}</button>
        <button wire:click="gotoToday" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">{{ __('Today') }}</button>
        <button wire:click="shift(7)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Week →') }}</button>
        <a href="{{ route('pricing.calendar') }}"
           class="ml-auto rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Pricing calendar') }}</a>
        <a href="{{ route('pricing.bulk') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Bulk update') }}</a>
    </div>

    <div class="overflow-x-auto scroll-smooth rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-max border-collapse text-xs">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 w-40 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left font-semibold text-slate-700">{{ __('Room type') }}</th>
                    @foreach ($days as $d)
                        @php $c = \Illuminate\Support\Carbon::parse($d); @endphp
                        <th class="w-20 border-b border-slate-200 px-1 py-1 text-center font-medium text-slate-500
                                   {{ $c->isToday() ? 'bg-yellow-50 ring-1 ring-yellow-300' : '' }}
                                   {{ $c->isWeekend() ? 'bg-slate-50' : '' }}">
                            <div class="text-[10px] uppercase">{{ $c->isoFormat('dd') }}</div>
                            <div class="text-sm font-semibold text-slate-700">{{ $c->format('j') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr>
                        <td class="sticky left-0 z-10 border-b border-r border-slate-200 bg-white px-3 py-2 align-middle">
                            <div class="font-semibold text-slate-900">{{ $type->name }}</div>
                        </td>
                        @foreach ($days as $d)
                            @php $cell = $matrix[$type->id][$d]; @endphp
                            <td class="border-b border-slate-100 p-1 align-top text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <input type="number" min="0" max="30" value="{{ $cell['min'] ?? '' }}"
                                           wire:change="setMinStay({{ $type->id }}, '{{ $d }}', parseInt($event.target.value || 0))"
                                           placeholder="—"
                                           class="w-14 rounded border-slate-200 px-1 py-0.5 text-center text-xs">
                                    <div class="flex gap-1 text-[9px]">
                                        <button wire:click="toggleCta({{ $type->id }}, '{{ $d }}')"
                                                class="rounded px-1 py-0.5 font-mono {{ $cell['cta'] ? 'bg-red-100 text-red-700 ring-1 ring-red-300' : 'bg-slate-100 text-slate-400 hover:bg-slate-200' }}">CTA</button>
                                        <button wire:click="toggleCtd({{ $type->id }}, '{{ $d }}')"
                                                class="rounded px-1 py-0.5 font-mono {{ $cell['ctd'] ? 'bg-red-100 text-red-700 ring-1 ring-red-300' : 'bg-slate-100 text-slate-400 hover:bg-slate-200' }}">CTD</button>
                                    </div>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">
        {{ __('Type a number to set minimum stay for that day. Click CTA / CTD to close arrival / departure.') }}
    </p>
</div>
