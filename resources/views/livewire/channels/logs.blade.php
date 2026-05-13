<div>
    <x-slot name="header">{{ __('Sync log') }}: {{ $connection->name }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('channels.show', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Channel') }}</a>
        <a href="{{ route('channels.mappings', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('Mappings') }}</a>

        <div class="ml-auto flex gap-2">
            <select wire:model.live="filterAction" class="rounded-md border-slate-300 text-sm">
                <option value="">{{ __('All actions') }}</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}">{{ __(ucfirst(str_replace('_', ' ', $a))) }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterStatus" class="rounded-md border-slate-300 text-sm">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}">{{ __(ucfirst($s)) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-2">{{ __('Started') }}</th>
                    <th class="px-4 py-2">{{ __('Direction') }}</th>
                    <th class="px-4 py-2">{{ __('Action') }}</th>
                    <th class="px-4 py-2">{{ __('Status') }}</th>
                    <th class="px-4 py-2">{{ __('Duration') }}</th>
                    <th class="px-4 py-2">{{ __('Trigger') }}</th>
                    <th class="px-4 py-2">{{ __('Summary') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-2 text-xs text-slate-500">
                            {{ $log->started_at?->format('Y-m-d H:i:s') }}
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                {{ $log->direction === 'in' ? '⇣ ' . __('In') : '⇡ ' . __('Out') }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-slate-700">{{ __(ucfirst(str_replace('_', ' ', $log->action))) }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs ring-1 ring-inset
                                         {{ $log->status === 'success' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                           : ($log->status === 'partial' ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                               : 'bg-rose-50 text-rose-700 ring-rose-200') }}">
                                {{ __(ucfirst($log->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 tabular-nums text-slate-500 text-xs">{{ $log->duration_ms ? $log->duration_ms.' ms' : '—' }}</td>
                        <td class="px-4 py-2 text-xs text-slate-500">{{ __(ucfirst($log->triggered_by)) }}</td>
                        <td class="px-4 py-2 text-xs text-slate-500">
                            @if ($log->error)
                                <span class="text-rose-600">{{ \Illuminate\Support\Str::limit($log->error, 80) }}</span>
                            @elseif (is_array($log->response_summary) && $log->response_summary)
                                <code class="rounded bg-slate-50 px-1.5 py-0.5">{{ \Illuminate\Support\Str::limit(json_encode($log->response_summary), 80) }}</code>
                            @else
                                <span class="italic text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center italic text-slate-400">{{ __('No sync activity yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($logs->hasPages())
            <div class="border-t border-slate-100 px-4 py-2">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
