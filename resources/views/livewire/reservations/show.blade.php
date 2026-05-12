<div>
    <x-slot name="header">Reservation {{ $r->code }}</x-slot>

    @if ($flash)
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $error }}</div>
    @endif

    {{-- Action bar --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @if ($r->status === 'confirmed')
            <button wire:click="checkIn"
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Check in</button>
        @endif
        @if ($r->status === 'checked_in')
            <button wire:click="checkOut"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Check out</button>
        @endif
        @if (! in_array($r->status, ['cancelled', 'checked_out']))
            <button wire:click="openCancelModal"
                    class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Cancel</button>
        @endif

        @if (! in_array($r->status, ['cancelled']))
            <button wire:click="openPaymentModal"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">+ Payment</button>
            <button wire:click="openChargeModal"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">+ Charge</button>
        @endif

        @if ($r->invoice)
            <a href="{{ route('invoices.show', $r->invoice) }}"
               class="ml-auto rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">View invoice</a>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Left: summary --}}
        <section class="lg:col-span-2 space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid grid-cols-2 gap-y-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Guest</dt>
                        <dd class="font-medium text-slate-900">{{ $r->leadGuest?->full_name ?? '—' }}</dd>
                        <dd class="text-xs text-slate-500">{{ $r->leadGuest?->phone }} · {{ $r->leadGuest?->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Room</dt>
                        <dd class="font-medium">{{ $r->room?->number ?? '—' }}</dd>
                        <dd class="text-xs text-slate-500">{{ $r->room?->roomType?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Dates</dt>
                        <dd class="font-medium">{{ $r->check_in_date->toDateString() }} → {{ $r->check_out_date->toDateString() }}</dd>
                        <dd class="text-xs text-slate-500">{{ $r->nights }} night{{ $r->nights === 1 ? '' : 's' }} · {{ $r->adults }} adult{{ $r->adults === 1 ? '' : 's' }}{{ $r->children ? ", $r->children child" : '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Status</dt>
                        <dd><x-status-pill :value="$r->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Payment</dt>
                        <dd><x-status-pill :value="$r->payment_status" kind="payment" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">Source</dt>
                        <dd class="font-medium">{{ $r->source }}</dd>
                    </div>
                </div>
                @if ($r->special_requests)
                    <div class="mt-4 rounded bg-slate-50 p-3 text-sm text-slate-700">
                        <div class="text-xs uppercase text-slate-500 mb-1">Special requests</div>
                        {{ $r->special_requests }}
                    </div>
                @endif
            </div>

            {{-- Folio --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">Folio</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500">
                            <th class="pb-2">Description</th><th class="pb-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($r->nightsBreakdown->sortBy('date') as $n)
                            <tr><td class="py-1.5">Night of {{ $n->date->toDateString() }}</td><td class="py-1.5 text-right">{{ number_format((float) $n->nightly_rate, 2) }}</td></tr>
                        @endforeach
                        @foreach ($r->charges as $c)
                            <tr>
                                <td class="py-1.5">
                                    <span class="text-xs uppercase text-slate-400 mr-1">[{{ $c->type }}]</span>
                                    {{ $c->description }}
                                </td>
                                <td class="py-1.5 text-right {{ $c->type === 'discount' ? 'text-emerald-700' : '' }}">
                                    {{ $c->type === 'discount' ? '−' : '' }}{{ number_format((float) $c->total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 text-sm">
                        <tr><td class="pt-2 text-right text-slate-500">Room total</td><td class="pt-2 text-right">{{ number_format((float) $r->room_rate_total, 2) }}</td></tr>
                        <tr><td class="text-right text-slate-500">Extras</td><td class="text-right">{{ number_format((float) $r->extras_total, 2) }}</td></tr>
                        @if ((float) $r->discount_total > 0)
                            <tr><td class="text-right text-slate-500">Discount</td><td class="text-right text-emerald-700">−{{ number_format((float) $r->discount_total, 2) }}</td></tr>
                        @endif
                        <tr class="font-semibold"><td class="pt-2 text-right">Grand total</td><td class="pt-2 text-right">{{ number_format((float) $r->grand_total, 2) }} {{ $r->currency }}</td></tr>
                        <tr><td class="text-right text-slate-500">Paid</td><td class="text-right">{{ number_format((float) $r->paid_total, 2) }} {{ $r->currency }}</td></tr>
                        <tr class="font-semibold"><td class="text-right">Balance</td><td class="text-right">{{ number_format((float) $r->grand_total - (float) $r->paid_total, 2) }} {{ $r->currency }}</td></tr>
                    </tfoot>
                </table>
            </div>

            {{-- Payments --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">Payments</h2>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($r->payments as $p)
                        <li class="flex justify-between py-2">
                            <div>
                                <div class="font-medium">{{ number_format((float) $p->amount, 2) }} {{ $p->currency }}</div>
                                <div class="text-xs text-slate-500">{{ $p->method }} · {{ optional($p->paid_at)->format('Y-m-d H:i') }} · by {{ $p->receivedBy?->name ?? '—' }}</div>
                            </div>
                            <x-status-pill :value="$p->status" kind="payment" />
                        </li>
                    @empty
                        <li class="py-4 text-center text-slate-400">No payments yet.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        {{-- Right: status history --}}
        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">Status history</h2>
                <ol class="relative border-s border-slate-200 ps-4 text-sm">
                    @foreach ($r->statusHistory->sortBy('changed_at') as $h)
                        <li class="mb-3 ms-2">
                            <div class="absolute -ms-[7px] mt-1 h-2.5 w-2.5 rounded-full bg-slate-400"></div>
                            <div class="font-medium">{{ $h->from_status ? "$h->from_status → $h->to_status" : "→ $h->to_status" }}</div>
                            <div class="text-xs text-slate-500">{{ optional($h->changed_at)->format('Y-m-d H:i') }} · {{ $h->changedBy?->name ?? '—' }}</div>
                            @if ($h->note)
                                <div class="text-xs text-slate-600 mt-0.5">{{ $h->note }}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </div>

    {{-- Payment modal --}}
    <div x-cloak x-show="$wire.showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @click.outside="$wire.showPaymentModal = false">
            <h3 class="text-lg font-semibold text-slate-900">Record payment</h3>
            <div class="mt-4 space-y-3 text-sm">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Method</label>
                    <select wire:model="payMethod" class="mt-1 w-full rounded-md border-slate-300">
                        @foreach (\App\Models\Payment::METHODS as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Amount ({{ $r->currency }})</label>
                    <input type="number" step="0.01" wire:model="payAmount" class="mt-1 w-full rounded-md border-slate-300">
                    @error('payAmount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Reference (optional)</label>
                    <input wire:model="payReference" class="mt-1 w-full rounded-md border-slate-300">
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button wire:click="$set('showPaymentModal', false)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium">Cancel</button>
                <button wire:click="recordPayment" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Record</button>
            </div>
        </div>
    </div>

    {{-- Charge modal --}}
    <div x-cloak x-show="$wire.showChargeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @click.outside="$wire.showChargeModal = false">
            <h3 class="text-lg font-semibold text-slate-900">Add charge</h3>
            <div class="mt-4 space-y-3 text-sm">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Type</label>
                    <select wire:model="chargeType" class="mt-1 w-full rounded-md border-slate-300">
                        @foreach (\App\Models\ReservationCharge::TYPES as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Description</label>
                    <input wire:model="chargeDescription" class="mt-1 w-full rounded-md border-slate-300">
                    @error('chargeDescription') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Amount ({{ $r->currency }})</label>
                    <input type="number" step="0.01" wire:model="chargeAmount" class="mt-1 w-full rounded-md border-slate-300">
                    @error('chargeAmount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button wire:click="$set('showChargeModal', false)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium">Cancel</button>
                <button wire:click="addCharge" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add</button>
            </div>
        </div>
    </div>

    {{-- Cancel modal --}}
    <div x-cloak x-show="$wire.showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @click.outside="$wire.showCancelModal = false">
            <h3 class="text-lg font-semibold text-slate-900">Cancel reservation?</h3>
            <p class="mt-2 text-sm text-slate-600">This releases the room. You can't undo it.</p>
            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Reason</label>
                <textarea wire:model="cancelReason" rows="3" class="mt-1 w-full rounded-md border-slate-300"></textarea>
                @error('cancelReason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button wire:click="$set('showCancelModal', false)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium">Keep reservation</button>
                <button wire:click="confirmCancel" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Cancel reservation</button>
            </div>
        </div>
    </div>
</div>
