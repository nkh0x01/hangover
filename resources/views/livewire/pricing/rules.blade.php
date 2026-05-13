<div>
    <x-slot name="header">{{ __('Pricing rules') }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('pricing.calendar') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Pricing calendar') }}</a>
        <button wire:click="openCreate"
                class="ml-auto rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">{{ __('+ New rule') }}</button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-right">{{ __('Priority') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Name') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Type') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Scope') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Action') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Valid') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Status') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rules as $r)
                    <tr>
                        <td class="px-4 py-2 text-right text-slate-500">{{ $r->priority }}</td>
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $r->name }}</td>
                        <td class="px-4 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ __(str_replace('_', ' ', $r->type)) }}</span></td>
                        <td class="px-4 py-2 text-slate-700">
                            {{ __(str_replace('_', ' ', $r->scope)) }}
                            @if ($r->room_type_id)
                                <span class="text-xs text-slate-500">· {{ $r->roomType?->name }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-700">
                            @php
                                $a = $r->action ?? [];
                                $sign = $a['value'] >= 0 ? '+' : '';
                            @endphp
                            @if (($a['type'] ?? '') === 'percent')
                                {{ $sign }}{{ $a['value'] }}%
                            @elseif (($a['type'] ?? '') === 'set')
                                = {{ number_format((float) $a['value'], 2) }}
                            @else
                                {{ $sign }}{{ number_format((float) $a['value'], 2) }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-500">
                            @if ($r->valid_from || $r->valid_to)
                                {{ optional($r->valid_from)->toDateString() ?? '…' }} → {{ optional($r->valid_to)->toDateString() ?? '…' }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($r->active)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700 ring-1 ring-emerald-200">{{ __('active') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 ring-1 ring-slate-200">{{ __('disabled') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="openEdit({{ $r->id }})" class="text-xs text-slate-500 hover:text-slate-800">{{ __('Edit') }}</button>
                            <button wire:click="toggle({{ $r->id }})" class="ml-2 text-xs text-slate-500 hover:text-slate-800">{{ $r->active ? __('Disable') : __('Enable') }}</button>
                            <button wire:click="delete({{ $r->id }})"
                                    onclick="return confirm('{{ __('Delete this rule?') }}')"
                                    class="ml-2 text-xs text-red-400 hover:text-red-700">{{ __('Delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">{{ __('No pricing rules yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Form modal --}}
    <div x-cloak x-show="$wire.showForm"
         @keydown.window.escape="$wire.showForm = false"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div x-show="$wire.showForm"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             @click.outside="$wire.showForm = false"
             class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-semibold text-slate-900">{{ $editingId ? __('Edit pricing rule') : __('+ New pricing rule') }}</h3>
                <span class="text-xs text-slate-400">{{ __('Esc to close') }}</span>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
                    <input wire:model="name" class="mt-1 w-full rounded-md border-slate-300">
                    @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Type') }}</label>
                    <select wire:model.live="type" class="mt-1 w-full rounded-md border-slate-300">
                        @foreach (\App\Models\PricingRule::TYPES as $t)
                            <option value="{{ $t }}">{{ __(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Priority') }}</label>
                    <input type="number" min="1" max="9999" wire:model="priority"
                           class="mt-1 w-full rounded-md border-slate-300">
                    <p class="mt-1 text-xs text-slate-500">{{ __('Lower priority runs first.') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Scope') }}</label>
                    <select wire:model.live="scope" class="mt-1 w-full rounded-md border-slate-300">
                        @foreach (\App\Models\PricingRule::SCOPES as $s)
                            <option value="{{ $s }}">{{ __(str_replace('_', ' ', $s)) }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($scope === \App\Models\PricingRule::SCOPE_ROOM_TYPE)
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Room type') }}</label>
                        <select wire:model="roomTypeId" class="mt-1 w-full rounded-md border-slate-300">
                            <option value="">—</option>
                            @foreach ($roomTypes as $rt)
                                <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Action --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Action') }}</label>
                    <select wire:model="actionType" class="mt-1 w-full rounded-md border-slate-300">
                        <option value="percent">{{ __('Percent (e.g. +15)') }}</option>
                        <option value="absolute">{{ __('Absolute (e.g. +50)') }}</option>
                        <option value="set">{{ __('Set to (replace price)') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Value') }}</label>
                    <input type="number" step="0.01" wire:model="actionValue"
                           class="mt-1 w-full rounded-md border-slate-300">
                </div>

                {{-- Per-type conditions --}}
                @if ($type === \App\Models\PricingRule::TYPE_WEEKEND)
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">{{ __('Days (ISO: Mon=1 … Sun=7)') }}</label>
                        <div class="mt-1 flex flex-wrap gap-2 text-xs">
                            @foreach ([1=>'Mo',2=>'Tu',3=>'We',4=>'Th',5=>'Fr',6=>'Sa',7=>'Su'] as $d => $label)
                                <label class="inline-flex items-center gap-1 rounded border border-slate-200 px-2 py-1">
                                    <input type="checkbox" value="{{ $d }}" wire:model="days" class="rounded border-slate-300">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($type === \App\Models\PricingRule::TYPE_HOLIDAY)
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">{{ __('Dates (YYYY-MM-DD, comma-separated)') }}</label>
                        <input wire:model="datesCsv" placeholder="2026-12-31, 2027-01-01"
                               class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                @endif

                @if ($type === \App\Models\PricingRule::TYPE_OCCUPANCY)
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Min occupancy (0-1)') }}</label>
                        <input type="number" step="0.01" min="0" max="1" wire:model="minOcc"
                               class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Max occupancy (0-1)') }}</label>
                        <input type="number" step="0.01" min="0" max="1" wire:model="maxOcc"
                               class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                @endif

                @if ($type === \App\Models\PricingRule::TYPE_LAST_MINUTE)
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Max days to arrival') }}</label>
                        <input type="number" min="0" wire:model="maxDaysToArrival"
                               class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                @endif

                @if ($type === \App\Models\PricingRule::TYPE_LENGTH_OF_STAY)
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Minimum nights') }}</label>
                        <input type="number" min="1" wire:model="minNights"
                               class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Valid from') }}</label>
                    <input type="date" wire:model="validFrom"
                           class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Valid to') }}</label>
                    <input type="date" wire:model="validTo"
                           class="mt-1 w-full rounded-md border-slate-300">
                </div>

                <label class="inline-flex items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" wire:model="active" class="rounded border-slate-300">
                    {{ __('Active') }}
                </label>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button wire:click="$set('showForm', false)"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Cancel') }}</button>
                <button wire:click="save"
                        wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                    <x-spinner wire:loading wire:target="save" class="h-4 w-4 -ml-1" />
                    {{ __('Save') }}
                </button>
            </div>
        </div>
    </div>
</div>
