<div>
    <x-slot name="header">New reservation</x-slot>

    {{-- Stepper --}}
    <ol class="mb-6 flex items-center gap-2 text-xs font-medium">
        @foreach ([
            1 => 'Dates',
            2 => 'Room',
            3 => 'Guest',
            4 => 'Confirm',
        ] as $idx => $label)
            <li class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full text-[11px]
                             {{ $step >= $idx ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-500' }}">{{ $idx }}</span>
                <span class="{{ $step === $idx ? 'text-slate-900' : 'text-slate-500' }}">{{ $label }}</span>
                @if ($idx < 4)
                    <span class="mx-1 h-px w-6 bg-slate-300"></span>
                @endif
            </li>
        @endforeach
    </ol>

    @if ($error)
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $error }}</div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        @if ($step === 1)
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Check-in</label>
                    <input type="date" wire:model.live="checkIn"
                           class="mt-1 w-full rounded-md border-slate-300">
                    @error('checkIn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Check-out</label>
                    <input type="date" wire:model.live="checkOut"
                           class="mt-1 w-full rounded-md border-slate-300">
                    @error('checkOut') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Adults</label>
                    <input type="number" min="1" max="8" wire:model="adults"
                           class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Children</label>
                    <input type="number" min="0" max="8" wire:model="children"
                           class="mt-1 w-full rounded-md border-slate-300">
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">{{ $nights }} night{{ $nights === 1 ? '' : 's' }}.</p>
        @endif

        @if ($step === 2)
            <p class="mb-3 text-sm text-slate-600">{{ $nights }} night{{ $nights === 1 ? '' : 's' }} from {{ $checkIn }} to {{ $checkOut }}.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rooms as $room)
                    @php
                        $disabled = ! $room->is_available;
                        $selected = $roomId === $room->id;
                    @endphp
                    <button type="button"
                            wire:click="pickRoom({{ $room->id }})"
                            @disabled($disabled)
                            class="rounded-lg border p-4 text-left transition
                                   {{ $disabled ? 'cursor-not-allowed border-slate-100 bg-slate-50 opacity-60' : 'cursor-pointer hover:border-slate-400' }}
                                   {{ $selected ? 'border-slate-900 bg-slate-50 ring-2 ring-slate-900' : 'border-slate-200' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold text-slate-900">{{ $room->number }}</span>
                            @if ($disabled)
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">unavailable</span>
                            @elseif ($selected)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-700">picked</span>
                            @endif
                        </div>
                        <div class="mt-1 text-sm text-slate-700">{{ $room->roomType?->name }}</div>
                        <div class="mt-1 text-xs text-slate-500">Floor {{ $room->floor }} · {{ $room->roomType?->bed_type }}</div>
                        <div class="mt-3 text-sm font-medium text-slate-900">{{ number_format((float) $room->roomType?->base_price, 2) }} {{ $currency }} / night</div>
                    </button>
                @endforeach
            </div>
            @error('roomId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

            @if ($quote)
                <div class="mt-5 rounded-md bg-slate-50 p-4 text-sm">
                    <div class="font-medium text-slate-700">Quote</div>
                    <ul class="mt-2 space-y-0.5 text-slate-600">
                        @foreach ($quote->nights as $n)
                            <li class="flex justify-between">
                                <span>{{ $n->date->toDateString() }} {{ $n->weekendUplift ? '· weekend' : '' }}</span>
                                <span>{{ number_format($n->amount, 2) }}</span>
                            </li>
                        @endforeach
                        <li class="flex justify-between border-t border-slate-200 pt-1 mt-1 font-semibold text-slate-900">
                            <span>Total</span>
                            <span>{{ number_format($quote->total(), 2) }} {{ $currency }}</span>
                        </li>
                    </ul>
                </div>
            @endif
        @endif

        @if ($step === 3)
            @if ($guestId)
                @php $g = \App\Models\Guest::find($guestId); @endphp
                <div class="flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 p-3">
                    <div>
                        <div class="font-medium text-emerald-900">Returning guest: {{ $g?->full_name }}</div>
                        <div class="text-xs text-emerald-800">{{ $g?->phone }} · {{ $g?->email }}</div>
                    </div>
                    <button type="button" wire:click="clearExistingGuest"
                            class="text-sm text-emerald-700 underline">Change</button>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">First name</label>
                        <input wire:model.live.debounce.300ms="firstName"
                               class="mt-1 w-full rounded-md border-slate-300">
                        @error('firstName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Last name</label>
                        <input wire:model.live.debounce.300ms="lastName"
                               class="mt-1 w-full rounded-md border-slate-300">
                        @error('lastName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Phone</label>
                        <input wire:model.live.debounce.300ms="phone"
                               class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Email</label>
                        <input type="email" wire:model="email"
                               class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Country</label>
                        <input wire:model="country" maxlength="2" placeholder="GE"
                               class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ID type</label>
                            <select wire:model="docType" class="mt-1 w-full rounded-md border-slate-300">
                                <option value="passport">passport</option>
                                <option value="id_card">id_card</option>
                                <option value="driver_license">driver_license</option>
                                <option value="other">other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ID number</label>
                            <input wire:model="docNumber" class="mt-1 w-full rounded-md border-slate-300">
                        </div>
                    </div>
                </div>

                @if ($guestSuggestions->isNotEmpty())
                    <div class="mt-4 rounded-md border border-slate-200">
                        <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500 uppercase tracking-wide">Existing guests</div>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($guestSuggestions as $g)
                                <li>
                                    <button type="button" wire:click="useExistingGuest({{ $g->id }})"
                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50">
                                        <span class="font-medium">{{ $g->full_name }}</span>
                                        <span class="text-xs text-slate-500">{{ $g->phone }} · {{ $g->email }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        @endif

        @if ($step === 4)
            @php
                $room = $roomId ? \App\Models\Room::with('roomType')->find($roomId) : null;
                $guest = $guestId ? \App\Models\Guest::find($guestId) : null;
                $quote = $room && $checkIn && $checkOut
                    ? app(\App\Domain\Pricing\PricingService::class)->priceForStay(
                        $room->roomType,
                        new \App\Domain\Availability\Period($checkIn, $checkOut)
                    )
                    : null;
            @endphp
            <div class="space-y-4">
                <div class="rounded-md bg-slate-50 p-4 text-sm">
                    <div class="grid gap-y-2 sm:grid-cols-2">
                        <div><dt class="text-xs uppercase text-slate-500">Guest</dt><dd class="font-medium">{{ $guest?->full_name ?? trim($firstName.' '.$lastName) }}</dd></div>
                        <div><dt class="text-xs uppercase text-slate-500">Room</dt><dd class="font-medium">{{ $room?->number }} · {{ $room?->roomType?->name }}</dd></div>
                        <div><dt class="text-xs uppercase text-slate-500">Dates</dt><dd class="font-medium">{{ $checkIn }} → {{ $checkOut }} ({{ $nights }} night{{ $nights === 1 ? '' : 's' }})</dd></div>
                        <div><dt class="text-xs uppercase text-slate-500">Occupancy</dt><dd class="font-medium">{{ $adults }} adult{{ $adults === 1 ? '' : 's' }}{{ $children ? ", $children child" : '' }}</dd></div>
                    </div>
                </div>

                @if ($quote)
                    <div class="rounded-md bg-white p-4 ring-1 ring-slate-200 text-sm">
                        <div class="font-medium text-slate-700 mb-2">Pricing</div>
                        <ul class="space-y-0.5 text-slate-600">
                            @foreach ($quote->nights as $n)
                                <li class="flex justify-between">
                                    <span>{{ $n->date->toDateString() }}{{ $n->weekendUplift ? ' · weekend' : '' }}</span>
                                    <span>{{ number_format($n->amount, 2) }}</span>
                                </li>
                            @endforeach
                            <li class="flex justify-between border-t border-slate-200 pt-1 mt-1 font-semibold text-slate-900">
                                <span>Total</span>
                                <span>{{ number_format($quote->total(), 2) }} {{ $currency }}</span>
                            </li>
                        </ul>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Source</label>
                        <select wire:model="source" class="mt-1 w-full rounded-md border-slate-300">
                            @foreach (\App\Models\Reservation::SOURCES as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Special requests</label>
                    <textarea wire:model="notes" rows="2" class="mt-1 w-full rounded-md border-slate-300"></textarea>
                </div>
            </div>
        @endif

        <div class="mt-6 flex items-center justify-between">
            <div>
                @if ($step > 1)
                    <button type="button" wire:click="prevStep"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">← Back</button>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('reservations.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
                @if ($step < 4)
                    <button type="button" wire:click="nextStep"
                            class="rounded-md bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Next →</button>
                @else
                    <button type="button" wire:click="create"
                            wire:loading.attr="disabled"
                            class="rounded-md bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                        <span wire:loading.remove>Create reservation</span>
                        <span wire:loading>Creating…</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
