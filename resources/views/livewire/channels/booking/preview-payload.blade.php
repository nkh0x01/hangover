<div>
    <x-slot name="header">{{ __('Preview payload') }}: {{ $connection->name }}</x-slot>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('channels.booking.show', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Booking.com connection') }}</a>
    </div>

    <div class="mb-4 rounded-md border border-slate-200 bg-white p-4 text-xs text-slate-600 shadow-sm">
        {{ __('Build the exact JSON body that would be sent to Booking.com for the selected window. This view is read-only: nothing is posted to the OTA, even when dry-run is OFF.') }}
    </div>

    <div class="grid gap-4 lg:grid-cols-4">
        <div class="lg:col-span-1 rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div>
                <label class="block text-xs text-slate-500">{{ __('Payload kind') }}</label>
                <select wire:model.live="kind" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    <option value="availability">{{ __('Availability') }}</option>
                    <option value="rates">{{ __('Rates') }}</option>
                    <option value="restrictions">{{ __('Restrictions') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('From') }}</label>
                <input type="date" wire:model.live="windowFrom" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">{{ __('To') }}</label>
                <input type="date" wire:model.live="windowTo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
            </div>
            <div class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                {{ __('Rows in payload') }}: <span class="font-medium tabular-nums text-slate-700">{{ $rowCount }}</span>
            </div>
        </div>

        <div class="lg:col-span-3 rounded-xl border border-slate-200 bg-slate-900 p-4 shadow-sm">
            <pre class="overflow-x-auto text-xs leading-relaxed text-emerald-200 font-mono whitespace-pre">{{ $payloadJson }}</pre>
        </div>
    </div>
</div>
