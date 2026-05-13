<div>
    <x-slot name="header">{{ __('Booking.com') }}: {{ $connection->name }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('channels.booking.index') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Booking.com') }}</a>
        <a href="{{ route('channels.booking.credentials', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Credentials') }}</a>
        <a href="{{ route('channels.booking.test', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Test connection') }}</a>
        <a href="{{ route('channels.booking.preview', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Preview payload') }}</a>
        <a href="{{ route('channels.booking.logs', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Sync log') }}</a>
    </div>

    {{-- Mode banner --}}
    @if ($connection->isDryRun())
        <div class="mb-4 rounded-md border-2 border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <div class="flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div class="flex-1">
                    <div class="font-semibold">{{ __('Dry-run mode is ON.') }}</div>
                    <div class="text-amber-800 text-xs mt-0.5">{{ __('Outgoing payloads are logged but NOT sent to Booking.com. No real reservations will move.') }}</div>
                </div>
                <button wire:click="toggleDryRun"
                        class="rounded-md border border-amber-400 bg-white px-3 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-100">{{ __('Switch to LIVE') }}</button>
            </div>
        </div>
    @else
        <div class="mb-4 rounded-md border-2 border-rose-300 bg-rose-50 p-4 text-sm text-rose-900">
            <div class="flex items-start gap-3">
                <span class="text-xl">🔴</span>
                <div class="flex-1">
                    <div class="font-semibold">{{ __('LIVE mode is ON. Outgoing pushes will hit Booking.com.') }}</div>
                    <div class="text-rose-800 text-xs mt-0.5">
                        {{ data_get($connection->settings, 'sandbox', true) ? __('Sandbox endpoint.') : __('PRODUCTION endpoint.') }}
                        {{ __('Each push still requires explicit per-action confirmation.') }}
                    </div>
                </div>
                <button wire:click="toggleDryRun"
                        class="rounded-md border border-rose-400 bg-white px-3 py-1.5 text-xs font-medium text-rose-800 hover:bg-rose-100">{{ __('Switch to dry-run') }}</button>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Manual sync') }}</h3>

                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-slate-500">{{ __('From') }}</label>
                        <input type="date" wire:model.live="windowFrom" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">{{ __('To') }}</label>
                        <input type="date" wire:model.live="windowTo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <button wire:click="requestPush('test')"
                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        🔌 {{ __('Test connection') }}
                    </button>
                    <button wire:click="requestPush('pull')"
                            class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                        ⇣ {{ __('Pull reservations') }}
                    </button>
                    <button wire:click="requestPush('push_availability')"
                            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                        ⇡ {{ __('Push availability') }}
                    </button>
                    <button wire:click="requestPush('push_rates')"
                            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                        ⇡ {{ __('Push rates') }}
                    </button>
                    <button wire:click="requestPush('push_restrictions')"
                            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                        ⇡ {{ __('Push restrictions') }}
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Connection details') }}</h3>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-400">{{ __('Status') }}</dt>
                        <dd class="text-slate-700">{{ __(ucfirst($connection->status)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">{{ __('Sandbox') }}</dt>
                        <dd class="text-slate-700">{{ data_get($connection->settings, 'sandbox', true) ? __('Yes') : __('No') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">{{ __('Error count') }}</dt>
                        <dd class="text-slate-700 tabular-nums">{{ $connection->error_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">{{ __('Conflicts') }}</dt>
                        <dd class="text-slate-700 tabular-nums">{{ $conflictCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">{{ __('Last pull') }}</dt>
                        <dd class="text-slate-700">{{ $connection->last_pull_at?->diffForHumans() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">{{ __('Last push') }}</dt>
                        <dd class="text-slate-700">{{ $connection->last_push_at?->diffForHumans() ?? '—' }}</dd>
                    </div>
                </dl>
                @if ($connection->last_error)
                    <div class="mt-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                        {{ $connection->last_error }}
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Recent activity') }}</h3>
            <ul class="space-y-2">
                @forelse ($recentLogs as $log)
                    <li class="flex items-start justify-between gap-2 text-xs">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-slate-700">
                                {{ $log->direction === 'in' ? '⇣' : '⇡' }} {{ __(ucfirst(str_replace('_', ' ', $log->action))) }}
                            </div>
                            <div class="text-slate-400">{{ $log->started_at?->diffForHumans() }}</div>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs ring-1 ring-inset
                                     {{ $log->status === 'success' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                       : ($log->status === 'partial' ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                           : 'bg-rose-50 text-rose-700 ring-rose-200') }}">
                            {{ __(ucfirst($log->status)) }}
                        </span>
                    </li>
                @empty
                    <li class="text-sm italic text-slate-400">{{ __('No syncs yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Live-push confirmation modal --}}
    @if ($pendingLiveAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="w-full max-w-md rounded-xl border border-rose-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <span class="text-3xl">🔴</span>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-slate-900">{{ __('Confirm LIVE push to Booking.com') }}</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ __('You are about to perform a LIVE action against Booking.com :endpoint.', [
                                'endpoint' => data_get($connection->settings, 'sandbox', true) ? __('sandbox') : __('production'),
                            ]) }}
                        </p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ __('Action') }}: <span class="font-mono font-semibold">{{ $pendingLiveAction }}</span>
                        </p>
                        <p class="mt-3 rounded-md bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-800">
                            {{ __('This will modify real data on the OTA side. Confirmation expires in 60 seconds and is single-use.') }}
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button wire:click="cancelLivePush"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Cancel') }}</button>
                    <button wire:click="confirmLivePush"
                            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">{{ __('Confirm and push live') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
