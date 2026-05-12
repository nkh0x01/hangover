<div>
    <x-slot name="header">Reservations</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="Search code, guest, phone, room…"
               class="w-72 rounded-md border-slate-300 text-sm">
        <select wire:model.live="statusFilter" class="rounded-md border-slate-300 text-sm">
            <option value="">All statuses</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
        </select>
        <select wire:model.live="paymentFilter" class="rounded-md border-slate-300 text-sm">
            <option value="">All payments</option>
            @foreach ($paymentStatuses as $p)
                <option value="{{ $p }}">{{ $p }}</option>
            @endforeach
        </select>
        <a href="{{ route('reservations.create') }}"
           class="ml-auto rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">+ New reservation</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left">Code</th>
                    <th class="px-4 py-2 text-left">Guest</th>
                    <th class="px-4 py-2 text-left">Room</th>
                    <th class="px-4 py-2 text-left">Dates</th>
                    <th class="px-4 py-2 text-right">Total</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Payment</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($reservations as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-900">
                            <a href="{{ route('reservations.show', $r) }}" class="hover:underline">{{ $r->code }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $r->leadGuest?->full_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $r->room?->number ?? '—' }} · <span class="text-slate-500">{{ $r->roomType?->name }}</span></td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $r->check_in_date->toDateString() }} → {{ $r->check_out_date->toDateString() }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $r->grand_total, 2) }} {{ $r->currency }}</td>
                        <td class="px-4 py-2"><x-status-pill :value="$r->status" /></td>
                        <td class="px-4 py-2"><x-status-pill :value="$r->payment_status" kind="payment" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No reservations.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $reservations->links() }}</div>
</div>
