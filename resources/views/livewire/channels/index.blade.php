<div>
    <x-slot name="header">{{ __('Channels') }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-500 max-w-xl">
            {{ __('Two-way sync between your PMS and external OTAs. Mock provider is enabled by default for development.') }}
        </p>
        <div class="flex items-center gap-2">
            <a href="{{ route('channels.booking.index') }}"
               class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">
                {{ __('Booking.com') }} →
            </a>
        <a href="{{ route('channels.conflicts') }}"
           class="rounded-md border px-3 py-1.5 text-sm
                  {{ $conflicts > 0
                      ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'
                      : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50' }}">
            {{ __('Conflicts') }}
            @if ($conflicts > 0)
                <span class="ml-1 inline-flex items-center rounded-full bg-amber-200 px-2 py-0.5 text-xs font-medium text-amber-900">{{ $conflicts }}</span>
            @endif
        </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-2">{{ __('Name') }}</th>
                    <th class="px-4 py-2">{{ __('Channel') }}</th>
                    <th class="px-4 py-2">{{ __('Status') }}</th>
                    <th class="px-4 py-2">{{ __('Rooms') }}</th>
                    <th class="px-4 py-2">{{ __('Rates') }}</th>
                    <th class="px-4 py-2">{{ __('Inbox') }}</th>
                    <th class="px-4 py-2">{{ __('Last sync') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($connections as $c)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800">{{ $c->name }}</div>
                            @if ($c->last_error)
                                <div class="text-xs text-rose-600">{{ \Illuminate\Support\Str::limit($c->last_error, 60) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ __(ucfirst(str_replace('_', ' ', $c->channel))) }}</td>
                        <td class="px-4 py-3">
                            @php
                                $tones = [
                                    'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'paused' => 'bg-slate-50 text-slate-600 ring-slate-200',
                                    'error'  => 'bg-rose-50 text-rose-700 ring-rose-200',
                                ];
                                $tone = $tones[$c->status] ?? $tones['paused'];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs ring-1 ring-inset {{ $tone }}">
                                {{ __(ucfirst($c->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $c->room_mappings_count }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $c->rate_mappings_count }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $c->channel_reservations_count }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            @if ($c->last_pull_at)
                                ↓ {{ $c->last_pull_at->diffForHumans() }}
                            @endif
                            @if ($c->last_push_at)
                                <div>↑ {{ $c->last_push_at->diffForHumans() }}</div>
                            @endif
                            @if (! $c->last_pull_at && ! $c->last_push_at)
                                <span class="italic text-slate-400">{{ __('Never') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('channels.show', $c) }}"
                               class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">{{ __('No channel connections yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
