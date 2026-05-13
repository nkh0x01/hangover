<div>
    <x-slot name="header">{{ __('Channel') }}: {{ $connection->name }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('channels.index') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Channels') }}</a>
        <a href="{{ route('channels.mappings', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Mappings') }}</a>
        <a href="{{ route('channels.logs', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Sync log') }}</a>
        <a href="{{ route('channels.conflicts') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Conflicts') }}</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">

            {{-- Summary card --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400">{{ __('Channel') }}</div>
                        <div class="text-lg font-medium text-slate-800">{{ __(ucfirst(str_replace('_', ' ', $connection->channel))) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400">{{ __('Status') }}</div>
                        @php
                            $tones = [
                                'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                'paused' => 'bg-slate-50 text-slate-600 ring-slate-200',
                                'error'  => 'bg-rose-50 text-rose-700 ring-rose-200',
                            ];
                            $tone = $tones[$connection->status] ?? $tones['paused'];
                        @endphp
                        <span class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs ring-1 ring-inset {{ $tone }}">
                            {{ __(ucfirst($connection->status)) }}
                        </span>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400">{{ __('Errors') }}</div>
                        <div class="text-lg font-medium text-slate-800 tabular-nums">{{ $connection->error_count }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400">{{ __('Inbox') }}</div>
                        <div class="text-lg font-medium text-slate-800 tabular-nums">{{ $inboxCount }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400">{{ __('Conflicts') }}</div>
                        <div class="text-lg font-medium {{ $conflictCount > 0 ? 'text-amber-700' : 'text-slate-800' }} tabular-nums">
                            {{ $conflictCount }}
                        </div>
                    </div>
                </div>

                @if ($connection->last_error)
                    <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        <span class="font-medium">{{ __('Last error') }}:</span> {{ $connection->last_error }}
                    </div>
                @endif
            </div>

            {{-- Manual sync controls --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700">{{ __('Manual sync') }}</h3>
                    <div class="text-xs text-slate-400">{{ __('Window') }}</div>
                </div>

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
                    <button wire:click="testConnection"
                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        🔌 {{ __('Test connection') }}
                    </button>
                    <button wire:click="pullReservations"
                            class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                        ⇣ {{ __('Pull reservations') }}
                    </button>
                    <button wire:click="pushAvailability"
                            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                        ⇡ {{ __('Push availability') }}
                    </button>
                    <button wire:click="pushRates"
                            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                        ⇡ {{ __('Push rates') }}
                    </button>
                    <button wire:click="pushRestrictions"
                            class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                        ⇡ {{ __('Push restrictions') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Recent activity --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Recent activity') }}</h3>
            <ul class="space-y-2">
                @forelse ($recentLogs as $log)
                    <li class="flex items-start justify-between gap-2 text-xs">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-slate-700">
                                {{ $log->direction === 'in' ? '⇣' : '⇡' }}
                                {{ __(ucfirst(str_replace('_', ' ', $log->action))) }}
                            </div>
                            <div class="text-slate-400">{{ $log->started_at?->diffForHumans() }}</div>
                            @if ($log->error)
                                <div class="text-rose-600 truncate">{{ \Illuminate\Support\Str::limit($log->error, 50) }}</div>
                            @endif
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs ring-1 ring-inset
                                     {{ $log->status === 'success' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                       : ($log->status === 'partial' ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                           : 'bg-rose-50 text-rose-700 ring-rose-200') }}">
                            {{ __(ucfirst($log->status)) }}
                        </span>
                    </li>
                @empty
                    <li class="text-sm text-slate-400 italic">{{ __('No syncs yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
