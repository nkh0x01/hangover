<div>
    <x-slot name="header">{{ __('Inventory locations') }}</x-slot>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left">{{ __('Name') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Type') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Room') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Distinct products') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Units on hand') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($locations as $loc)
                    @php $units = (int) $loc->stocks->sum('quantity'); @endphp
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $loc->name }}</td>
                        <td class="px-4 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ __(str_replace('_', ' ', $loc->type)) }}</span></td>
                        <td class="px-4 py-2 text-slate-700">{{ $loc->room?->number ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">{{ $loc->stocks_count }}</td>
                        <td class="px-4 py-2 text-right">{{ $units }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
