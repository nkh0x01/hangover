<div>
    <x-slot name="header">{{ __('Credentials') }}: {{ $connection->name }}</x-slot>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('channels.booking.show', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Booking.com connection') }}</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Booking.com credentials') }}</h3>
            <p class="text-xs text-slate-500 mb-4">
                {{ __('All credentials are encrypted at rest using Laravel\'s app key. They are never echoed back to the form — leave a field blank to keep the existing value.') }}
            </p>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs text-slate-500">{{ __('Hotel ID') }}</label>
                    <input type="text" wire:model="hotelId"
                           class="mt-1 w-full rounded-md border-slate-300 text-sm font-mono">
                    @error('hotelId')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs text-slate-500">{{ __('API secret') }}
                        @if ($hasSecret)
                            <span class="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                {{ __('Stored') }}
                            </span>
                        @endif
                    </label>
                    <input type="password" wire:model="secret" autocomplete="new-password"
                           placeholder="{{ $hasSecret ? __('Leave blank to keep existing') : __('Paste Booking.com API secret') }}"
                           class="mt-1 w-full rounded-md border-slate-300 text-sm font-mono">
                </div>

                <div>
                    <label class="block text-xs text-slate-500">{{ __('Webhook secret') }}
                        @if ($hasWebhookSecret)
                            <span class="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                {{ __('Stored') }}
                            </span>
                        @endif
                    </label>
                    <div class="mt-1 flex gap-2">
                        <input type="text" wire:model="webhookSecret"
                               placeholder="{{ $hasWebhookSecret ? __('Leave blank to keep existing') : __('Generate or paste') }}"
                               class="flex-1 rounded-md border-slate-300 text-sm font-mono">
                        <button type="button" wire:click="generateWebhookSecret"
                                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs hover:bg-slate-50">{{ __('Generate') }}</button>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Used to verify HMAC signatures on inbound webhooks.') }}</p>
                </div>

                <div class="flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <input type="checkbox" wire:model="sandbox" class="rounded border-amber-400">
                    <span>{{ __('Use Booking.com SANDBOX endpoint (recommended until production credentials are validated)') }}</span>
                </div>

                <div class="flex justify-end">
                    <button wire:click="save"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">{{ __('Save') }}</button>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 mb-2">{{ __('Webhook URL') }}</h3>
                <p class="text-xs text-slate-500 mb-2">{{ __('Configure this URL on the Booking.com side so cancellations and modifications reach us.') }}</p>
                <code class="block break-all rounded bg-slate-50 px-2 py-1.5 text-xs text-slate-700">{{ $webhookUrl }}</code>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900">
                <div class="font-semibold mb-1">{{ __('Before going live') }}</div>
                <ul class="list-disc pl-4 space-y-1">
                    <li>{{ __('Validate credentials with Test connection.') }}</li>
                    <li>{{ __('Verify availability + rate payloads with Preview payload.') }}</li>
                    <li>{{ __('Push a small range first.') }}</li>
                    <li>{{ __('Keep dry-run ON until everything looks right.') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
