<div>
    <x-slot name="header">{{ __('Channel conflicts') }}</x-slot>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('channels.index') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Channels') }}</a>
    </div>

    <p class="mb-4 text-sm text-slate-500 max-w-2xl">
        {{ __('Inbound reservations that could not be placed automatically. Conflicts never overwrite a local booking — resolve them manually below.') }}
    </p>

    <div class="space-y-3">
        @forelse ($rows as $row)
            @php
                $payload = $row->raw_payload ?? [];
                $guest = $payload['guest'] ?? [];
                $tone = match ($row->status) {
                    'conflict'  => 'bg-amber-50 border-amber-200 text-amber-900',
                    'duplicate' => 'bg-indigo-50 border-indigo-200 text-indigo-900',
                    'failed'    => 'bg-rose-50 border-rose-200 text-rose-900',
                    default     => 'bg-slate-50 border-slate-200 text-slate-700',
                };
            @endphp
            <div class="rounded-xl border bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-400">{{ __('External ID') }}</div>
                        <div class="font-mono text-sm text-slate-800">{{ $row->external_id }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ $row->connection?->name }} · {{ __(ucfirst(str_replace('_', ' ', $row->connection?->channel ?? ''))) }}
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset {{ $tone }}">
                        {{ __(ucfirst($row->status)) }}
                    </span>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-4 text-sm">
                    <div>
                        <div class="text-xs text-slate-400">{{ __('Guest') }}</div>
                        <div class="text-slate-700">{{ ($guest['first_name'] ?? '—').' '.($guest['last_name'] ?? '') }}</div>
                        @if (! empty($guest['email']))
                            <div class="text-xs text-slate-500">{{ $guest['email'] }}</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">{{ __('Check-in / out') }}</div>
                        <div class="text-slate-700 tabular-nums">{{ $payload['check_in'] ?? '—' }} → {{ $payload['check_out'] ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">{{ __('Adults / children') }}</div>
                        <div class="text-slate-700 tabular-nums">{{ ($payload['adults'] ?? 0) }} / {{ ($payload['children'] ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">{{ __('Total') }}</div>
                        <div class="text-slate-700 tabular-nums">{{ ($payload['currency'] ?? '') }} {{ number_format((float) ($payload['total'] ?? 0), 2) }}</div>
                    </div>
                </div>

                @if ($row->error)
                    <div class="mt-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        {{ $row->error }}
                    </div>
                @endif

                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="dismiss({{ $row->id }})"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">{{ __('Dismiss') }}</button>
                    <button wire:click="retry({{ $row->id }})"
                            class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">{{ __('Retry import') }}</button>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                {{ __('No unresolved channel reservations. You\'re all caught up.') }}
            </div>
        @endforelse
    </div>
</div>
