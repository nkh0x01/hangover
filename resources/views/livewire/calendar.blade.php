<div>
    <x-slot name="header">{{ __('Calendar') }}</x-slot>

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <button wire:click="shift(-7)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Week') }}</button>
        <button wire:click="shift(-1)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Day') }}</button>
        <button wire:click="gotoToday" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">{{ __('Today') }}</button>
        <button wire:click="shift(1)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Day →') }}</button>
        <button wire:click="shift(7)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Week →') }}</button>
        <div class="ml-auto text-sm text-slate-500">
            {{ $start->isoFormat('MMM D, Y') }} → {{ $start->addDays(count($days))->isoFormat('MMM D, Y') }}
        </div>
    </div>

    {{-- Legend --}}
    <div class="mb-3 flex flex-wrap items-center gap-4 text-xs">
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-emerald-200 ring-1 ring-emerald-300"></span> {{ __('open') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-indigo-300 ring-1 ring-indigo-400"></span> {{ __('booked') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-zinc-300 ring-1 ring-zinc-400"></span> {{ __('blocked') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-slate-300 ring-1 ring-slate-400"></span> {{ __('maintenance') }}</span>
    </div>

    {{-- Grid --}}
    <div class="overflow-x-auto scroll-smooth rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-max border-collapse text-xs">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 w-32 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left font-semibold text-slate-700">{{ __('Room') }}</th>
                    @foreach ($days as $d)
                        @php $carbon = \Illuminate\Support\Carbon::parse($d); @endphp
                        <th class="w-14 border-b border-slate-200 px-1 py-1 text-center font-medium text-slate-500
                                   {{ $carbon->isToday() ? 'bg-yellow-50 ring-1 ring-yellow-300' : '' }}
                                   {{ $carbon->isWeekend() ? 'bg-slate-50' : '' }}">
                            <div class="text-[10px] uppercase">{{ $carbon->isoFormat('dd') }}</div>
                            <div class="text-sm font-semibold text-slate-700">{{ $carbon->format('j') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rooms as $room)
                    @php $daysList = $days->values(); @endphp
                    <tr>
                        <td class="sticky left-0 z-10 border-b border-r border-slate-200 bg-white px-3 py-2 align-middle">
                            <div class="font-semibold text-slate-900">{{ $room->number }}</div>
                            <div class="text-[10px] text-slate-500">{{ $room->roomType?->name }}</div>
                        </td>
                        @foreach ($daysList as $i => $d)
                            @php
                                $cell = $matrix[$room->id][$d] ?? null;
                                $status = $cell?->status ?? 'open';
                                $reservation = $cell?->reservation_id ? ($reservations[$cell->reservation_id] ?? null) : null;
                                $prevCell = $i > 0 ? ($matrix[$room->id][$daysList[$i-1]] ?? null) : null;
                                $nextCell = $i < $daysList->count() - 1 ? ($matrix[$room->id][$daysList[$i+1]] ?? null) : null;
                                $isSpanStart = $reservation && (! $prevCell || $prevCell->reservation_id !== $cell->reservation_id);
                                $isSpanEnd   = $reservation && (! $nextCell || $nextCell->reservation_id !== $cell->reservation_id);
                                $cls = match ($status) {
                                    'booked'      => 'bg-indigo-300 hover:bg-indigo-400 text-indigo-900',
                                    'blocked'     => 'bg-zinc-300 hover:bg-zinc-400 text-zinc-900',
                                    'maintenance' => 'bg-slate-300 hover:bg-slate-400 text-slate-900',
                                    default       => 'bg-emerald-50 hover:bg-emerald-200 text-emerald-900',
                                };
                                // Rounded only on span ends, plus a margin so spans visually connect.
                                $shape = $reservation
                                    ? ($isSpanStart ? 'rounded-l-md ml-0.5 ' : '').($isSpanEnd ? 'rounded-r-md mr-0.5 ' : '')
                                    : '';
                                $title = $reservation
                                    ? ($reservation->leadGuest?->full_name.' · '.$reservation->code.' · '.$reservation->status)
                                    : ucfirst($status);
                            @endphp
                            @if ($reservation)
                                <td class="border-b border-slate-100 p-0">
                                    <a href="{{ route('reservations.show', $reservation) }}"
                                       title="{{ $title }}"
                                       class="flex h-9 w-14 items-center justify-center {{ $cls }} {{ $shape }} text-[11px] font-medium transition-colors"
                                       aria-label="Reservation {{ $reservation->code }} for {{ $reservation->leadGuest?->full_name }}">
                                        @if ($isSpanStart)
                                            <span class="px-1 truncate">{{ $reservation->leadGuest?->full_name }}</span>
                                        @endif
                                    </a>
                                </td>
                            @else
                                <td class="border-b border-slate-100 p-0">
                                    <a href="{{ route('reservations.create', ['room' => $room->id, 'date' => $d]) }}"
                                       title="{{ $title }} — click to book"
                                       class="block h-9 w-14 {{ $cls }} transition-colors"></a>
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">
        {{ __('Click an open cell to start a reservation. Click a booked span to open the reservation detail.') }}
        <span class="ml-2">{{ __('Press') }} <kbd class="rounded bg-slate-100 px-1.5 text-[10px] font-mono text-slate-600">?</kbd> {{ __('for shortcuts.') }}</span>
    </p>
</div>
