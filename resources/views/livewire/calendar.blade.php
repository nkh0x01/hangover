<div>
    <x-slot name="header">Calendar</x-slot>

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <button wire:click="shift(-7)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">← Week</button>
        <button wire:click="shift(-1)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">← Day</button>
        <button wire:click="gotoToday" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Today</button>
        <button wire:click="shift(1)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Day →</button>
        <button wire:click="shift(7)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Week →</button>
        <div class="ml-auto text-sm text-slate-500">
            {{ $start->format('M j, Y') }} → {{ $start->addDays(count($days))->format('M j, Y') }}
        </div>
    </div>

    {{-- Legend --}}
    <div class="mb-3 flex flex-wrap items-center gap-4 text-xs">
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-emerald-200 ring-1 ring-emerald-300"></span> open</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-indigo-300 ring-1 ring-indigo-400"></span> booked</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-zinc-300 ring-1 ring-zinc-400"></span> blocked</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-slate-300 ring-1 ring-slate-400"></span> maintenance</span>
    </div>

    {{-- Grid --}}
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-max border-collapse text-xs">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 w-32 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left font-semibold text-slate-700">Room</th>
                    @foreach ($days as $d)
                        @php $carbon = \Illuminate\Support\Carbon::parse($d); @endphp
                        <th class="w-12 border-b border-slate-200 px-1 py-1 text-center font-medium text-slate-500
                                   {{ $carbon->isToday() ? 'bg-yellow-50' : '' }}
                                   {{ $carbon->isWeekend() ? 'bg-slate-50' : '' }}">
                            <div class="text-[10px] uppercase">{{ $carbon->format('D') }}</div>
                            <div class="text-sm font-semibold text-slate-700">{{ $carbon->format('j') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rooms as $room)
                    <tr>
                        <td class="sticky left-0 z-10 border-b border-r border-slate-200 bg-white px-3 py-2 align-middle">
                            <div class="font-semibold text-slate-900">{{ $room->number }}</div>
                            <div class="text-[10px] text-slate-500">{{ $room->roomType?->name }}</div>
                        </td>
                        @foreach ($days as $d)
                            @php
                                $cell = $matrix[$room->id][$d] ?? null;
                                $status = $cell?->status ?? 'open';
                                $reservation = $cell?->reservation_id ? ($reservations[$cell->reservation_id] ?? null) : null;
                                $cls = match ($status) {
                                    'booked'      => 'bg-indigo-300 hover:bg-indigo-400 text-indigo-900',
                                    'blocked'     => 'bg-zinc-300 hover:bg-zinc-400 text-zinc-900',
                                    'maintenance' => 'bg-slate-300 hover:bg-slate-400 text-slate-900',
                                    default       => 'bg-emerald-100 hover:bg-emerald-200 text-emerald-900',
                                };
                                $title = $reservation
                                    ? ($reservation->leadGuest?->full_name.' · '.$reservation->code)
                                    : ucfirst($status);
                            @endphp
                            @if ($reservation)
                                <td class="border-b border-slate-100 p-0">
                                    <a href="{{ route('reservations.show', $reservation) }}"
                                       title="{{ $title }}"
                                       class="block w-12 h-9 {{ $cls }} text-center text-[10px] leading-9 font-medium"
                                       aria-label="Reservation {{ $reservation->code }}">
                                        {{ \Illuminate\Support\Str::limit($reservation->leadGuest?->first_name ?? '', 4, '') }}
                                    </a>
                                </td>
                            @else
                                <td class="border-b border-slate-100 p-0">
                                    <a href="{{ route('reservations.create', ['room' => $room->id, 'date' => $d]) }}"
                                       title="{{ $title }} — click to book"
                                       class="block w-12 h-9 {{ $cls }} text-center text-[10px] leading-9"></a>
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">Click an open cell to start a reservation. Click a booked cell to open the reservation detail.</p>
</div>
