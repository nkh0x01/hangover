<div>
    <x-slot name="header">{{ __('Inventory movements') }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('Search product…') }}"
               class="w-72 rounded-md border-slate-300 text-sm">
        <select wire:model.live="typeFilter" class="rounded-md border-slate-300 text-sm">
            <option value="">{{ __('All types') }}</option>
            @foreach ($types as $t)
                <option value="{{ $t }}">{{ __($t) }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left">{{ __('When') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Type') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Product') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('From') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('To') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Quantity') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('By') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Note') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movements as $m)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-slate-700">{{ optional($m->occurred_at)->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ __($m->type) }}</span></td>
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $m->product?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $m->fromLocation?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $m->toLocation?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">{{ $m->quantity }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $m->user?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $m->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">{{ __('No movements yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $movements->links() }}</div>
</div>
