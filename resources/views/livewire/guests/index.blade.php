<div>
    <x-slot name="header">Guests</x-slot>

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="Search by name, phone, email, ID number…"
               class="w-full sm:w-96 rounded-md border-slate-300 text-sm">
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left">Name</th>
                    <th class="px-4 py-2 text-left">Phone</th>
                    <th class="px-4 py-2 text-left">Email</th>
                    <th class="px-4 py-2 text-left">Country</th>
                    <th class="px-4 py-2 text-left">Document</th>
                    <th class="px-4 py-2 text-right">Stays</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($guests as $g)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-900">
                            {{ $g->full_name }}
                            @if ($g->vip)
                                <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">VIP</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-700">{{ $g->phone ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-700">{{ $g->email ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-700">{{ $g->country ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-700">
                            @if ($g->doc_number)
                                <span class="text-xs uppercase text-slate-500">{{ $g->doc_type }}</span> {{ $g->doc_number }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">{{ $g->reservations_as_lead_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No guests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $guests->links() }}</div>
</div>
