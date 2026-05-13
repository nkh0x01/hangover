<div>
    <x-slot name="header">{{ __('Mappings') }}: {{ $connection->name }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('channels.show', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Channel') }}</a>
        <a href="{{ route('channels.logs', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Sync log') }}</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Room mappings --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Room mappings') }}</h3>

            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-2 py-2">{{ __('External ID') }}</th>
                        <th class="px-2 py-2">{{ __('External name') }}</th>
                        <th class="px-2 py-2">{{ __('Room type') }}</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rooms as $m)
                        <tr>
                            <td class="px-2 py-2 font-mono text-xs text-slate-700">{{ $m->external_room_id }}</td>
                            <td class="px-2 py-2 text-slate-600">{{ $m->external_room_name }}</td>
                            <td class="px-2 py-2 text-slate-600">{{ $m->roomType?->name }}</td>
                            <td class="px-2 py-2 text-right">
                                <button wire:click="deleteRoomMapping({{ $m->id }})"
                                        class="text-xs text-rose-600 hover:underline">{{ __('Remove') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-2 py-3 text-center italic text-slate-400">{{ __('No mappings yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4 rounded-md border border-dashed border-slate-300 p-3">
                <div class="text-xs font-medium text-slate-500 mb-2">{{ __('Add mapping') }}</div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" wire:model="newRoomExternalId" placeholder="{{ __('External room ID') }}"
                           class="rounded-md border-slate-300 text-sm">
                    <input type="text" wire:model="newRoomExternalName" placeholder="{{ __('External name (optional)') }}"
                           class="rounded-md border-slate-300 text-sm">
                    <select wire:model="newRoomTypeId" class="rounded-md border-slate-300 text-sm">
                        <option value="">{{ __('Select room type…') }}</option>
                        @foreach ($roomTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <button wire:click="addRoomMapping"
                            class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">{{ __('Add') }}</button>
                </div>
            </div>
        </div>

        {{-- Rate mappings --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Rate mappings') }}</h3>

            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-2 py-2">{{ __('External rate') }}</th>
                        <th class="px-2 py-2">{{ __('Room type') }}</th>
                        <th class="px-2 py-2">{{ __('Markup') }}</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rates as $r)
                        <tr>
                            <td class="px-2 py-2">
                                <div class="font-mono text-xs text-slate-700">{{ $r->external_rate_id }}</div>
                                <div class="text-xs text-slate-500">{{ $r->external_rate_name }}</div>
                            </td>
                            <td class="px-2 py-2 text-slate-600">{{ $r->roomType?->name }}</td>
                            <td class="px-2 py-2 text-slate-600">
                                @if ($r->markup_percent !== null)
                                    {{ number_format((float) $r->markup_percent, 2) }}%
                                @endif
                                @if ($r->markup_abs !== null)
                                    +{{ number_format((float) $r->markup_abs, 2) }}
                                @endif
                                @if ($r->markup_percent === null && $r->markup_abs === null)
                                    <span class="italic text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right">
                                <button wire:click="deleteRateMapping({{ $r->id }})"
                                        class="text-xs text-rose-600 hover:underline">{{ __('Remove') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-2 py-3 text-center italic text-slate-400">{{ __('No rate mappings yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4 rounded-md border border-dashed border-slate-300 p-3">
                <div class="text-xs font-medium text-slate-500 mb-2">{{ __('Add rate') }}</div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" wire:model="newRateExternalId" placeholder="{{ __('External rate ID') }}"
                           class="rounded-md border-slate-300 text-sm">
                    <input type="text" wire:model="newRateExternalName" placeholder="{{ __('Rate name') }}"
                           class="rounded-md border-slate-300 text-sm">
                    <select wire:model="newRateRoomTypeId" class="rounded-md border-slate-300 text-sm">
                        <option value="">{{ __('Select room type…') }}</option>
                        @foreach ($roomTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" wire:model="newRateMarkupPercent" placeholder="{{ __('Markup %') }}"
                           class="rounded-md border-slate-300 text-sm">
                    <button wire:click="addRateMapping"
                            class="sm:col-span-2 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">{{ __('Add') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
