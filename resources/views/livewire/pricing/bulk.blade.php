<div>
    <x-slot name="header">{{ __('Bulk price update') }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('pricing.calendar') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Pricing calendar') }}</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Start date') }}</label>
                    <input type="date" wire:model="startDate" class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('End date') }}</label>
                    <input type="date" wire:model="endDate" class="mt-1 w-full rounded-md border-slate-300">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Room types') }}</label>
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($types as $t)
                        <label class="inline-flex items-center gap-2 rounded border border-slate-200 bg-white px-3 py-1.5 text-sm">
                            <input type="checkbox" value="{{ $t->id }}" wire:model="roomTypeIds" class="rounded border-slate-300">
                            <span>{{ $t->name }}</span>
                            <span class="text-xs text-slate-400">({{ number_format((float) $t->base_price, 2) }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Action') }}</label>
                    <select wire:model.live="mode" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <option value="set">{{ __('Set absolute price') }}</option>
                        <option value="percent">{{ __('Percent from base (e.g. +15)') }}</option>
                        <option value="clear">{{ __('Clear override (restore engine price)') }}</option>
                    </select>
                </div>
                @if ($mode !== 'clear')
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Value') }}</label>
                        <input type="number" step="0.01" wire:model="value" class="mt-1 w-full rounded-md border-slate-300">
                    </div>
                @endif
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="weekendsOnly" class="rounded border-slate-300">
                {{ __('Weekends only (Fri / Sat)') }}
            </label>

            <div class="rounded-lg border border-slate-200 p-4 bg-slate-50">
                <label class="inline-flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" wire:model.live="applyRestrictions" class="rounded border-slate-300">
                    {{ __('Also set restrictions for these days') }}
                </label>
                @if ($applyRestrictions)
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">{{ __('Minimum stay') }}</label>
                            <input type="number" min="0" wire:model="minStay" class="mt-1 w-full rounded-md border-slate-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">{{ __('Maximum stay') }}</label>
                            <input type="number" min="0" wire:model="maxStay" class="mt-1 w-full rounded-md border-slate-300">
                        </div>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="cta" class="rounded border-slate-300">
                            <span>{{ __('Closed to arrival') }}</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="ctd" class="rounded border-slate-300">
                            <span>{{ __('Closed to departure') }}</span>
                        </label>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <button wire:click="apply"
                        wire:loading.attr="disabled" wire:target="apply"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                    <x-spinner wire:loading wire:target="apply" class="h-4 w-4 -ml-1" />
                    {{ __('Apply to selected days') }}
                </button>
            </div>
        </div>

        <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm text-sm space-y-3">
            <h2 class="font-semibold text-slate-700">{{ __('How this works') }}</h2>
            <p class="text-slate-600">{{ __('Pick a date range and one or more room types, then choose what to do:') }}</p>
            <ul class="list-disc pl-5 text-slate-600 space-y-1">
                <li>{{ __('"Set absolute price" overrides the engine for each day.') }}</li>
                <li>{{ __('"Percent from base" sets price = base × (1 + value%).') }}</li>
                <li>{{ __('"Clear override" removes any manual override (engine takes over again).') }}</li>
            </ul>
            <p class="text-slate-600">{{ __('Manual overrides always win over pricing rules.') }}</p>
        </aside>
    </div>
</div>
