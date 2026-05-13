<div>
    <x-slot name="header">{{ __('Pricing calendar') }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <button wire:click="shift(-7)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Week') }}</button>
        <button wire:click="shift(-1)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Day') }}</button>
        <button wire:click="gotoToday" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">{{ __('Today') }}</button>
        <button wire:click="shift(1)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Day →') }}</button>
        <button wire:click="shift(7)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Week →') }}</button>
        <a href="{{ route('pricing.rules') }}"
           class="ml-auto rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Pricing rules') }}</a>
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
                            <div class="text-[10px] text-slate-500">{{ __('base') }}: {{ number_format((float) $type->base_price, 2) }}</div>
                        </td>
                        @foreach ($days as $d)
                            @php $cell = $matrix[$type->id][$d]; @endphp
                            <td class="border-b border-slate-100 p-0 align-top">
                                <button type="button" wire:click="edit({{ $type->id }}, '{{ $d }}')"
                                        class="block w-20 h-16 px-1 py-1 text-center transition
                                               {{ $cell['manual'] ? 'bg-amber-50 hover:bg-amber-100' : 'bg-white hover:bg-slate-50' }}
                                               {{ $cell['cta'] || $cell['ctd'] ? 'ring-1 ring-inset ring-red-300' : '' }}">
                                    <div class="text-sm font-semibold {{ $cell['manual'] ? 'text-amber-800' : 'text-slate-800' }}">
                                        {{ number_format($cell['price'], 0) }}
                                    </div>
                                    <div class="text-[9px] text-slate-500">
                                        @if ($cell['min']) {{ __('min') }} {{ $cell['min'] }} @endif
                                        @if ($cell['cta']) <span class="text-red-600">CTA</span> @endif
                                        @if ($cell['ctd']) <span class="text-red-600">CTD</span> @endif
                                    </div>
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">
        {{ __('Click a cell to set a manual override or per-day restrictions. Amber cells are manual overrides.') }}
    </p>

    {{-- Inline editor --}}
    <div x-cloak x-show="$wire.editingDate"
         @keydown.window.escape="$wire.cancelEdit()"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div x-show="$wire.editingDate"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             @click.outside="$wire.cancelEdit()"
             class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Set override') }} <span class="text-slate-400">· {{ $editingDate }}</span></h3>
                <span class="text-xs text-slate-400">{{ __('Esc to close') }}</span>
            </div>
            <div class="mt-4 space-y-3 text-sm">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Manual price') }} ({{ $currency }})</label>
                    <input type="number" step="0.01" wire:model="editPrice" placeholder="{{ __('leave empty for no override') }}"
                           class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Minimum stay') }}</label>
                    <input type="number" min="0" wire:model="editMinStay" placeholder="—"
                           class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="editCta" class="rounded border-slate-300">
                        <span>{{ __('Closed to arrival') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="editCtd" class="rounded border-slate-300">
                        <span>{{ __('Closed to departure') }}</span>
                    </label>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button wire:click="cancelEdit"
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
