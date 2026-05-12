<div>
    <x-slot name="header">{{ __('Reservation :code', ['code' => $r->code]) }}</x-slot>

    @if ($error)
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $error }}</div>
    @endif

    {{-- Action bar --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @if ($r->status === 'confirmed')
            <button wire:click="checkIn"
                    wire:loading.attr="disabled" wire:target="checkIn"
                    class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60 disabled:cursor-wait">
                <x-spinner wire:loading wire:target="checkIn" class="h-4 w-4 -ml-1" />
                <span>{{ __('Check in') }}</span>
                <kbd class="hidden sm:inline ml-1 rounded bg-emerald-700/40 px-1.5 text-[10px] font-mono text-white/80">C</kbd>
            </button>
        @endif
        @if ($r->status === 'checked_in')
            <button wire:click="checkOut"
                    wire:loading.attr="disabled" wire:target="checkOut"
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-wait">
                <x-spinner wire:loading wire:target="checkOut" class="h-4 w-4 -ml-1" />
                <span>{{ __('Check out') }}</span>
                <kbd class="hidden sm:inline ml-1 rounded bg-indigo-700/40 px-1.5 text-[10px] font-mono text-white/80">X</kbd>
            </button>
        @endif
        @if (! in_array($r->status, ['cancelled', 'checked_out']))
            <button wire:click="openCancelModal"
                    class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">{{ __('Cancel') }}</button>
        @endif

        @if (! in_array($r->status, ['cancelled']))
            <button wire:click="openPaymentModal"
                    class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <span>{{ __('+ Payment') }}</span>
                <kbd class="hidden sm:inline rounded bg-slate-100 px-1.5 text-[10px] font-mono text-slate-500">P</kbd>
            </button>
            <button wire:click="openChargeModal"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('+ Charge') }}</button>
        @endif

        @if ($r->invoice)
            <a href="{{ route('invoices.show', $r->invoice) }}"
               class="ml-auto rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('View invoice') }}</a>
        @endif
    </div>

    {{-- Reception keyboard shortcuts on this page --}}
    <div x-data="{}" @keydown.window.c.prevent="if (event.target.tagName === 'BODY' && {{ $r->status === 'confirmed' ? 'true' : 'false' }}) $wire.checkIn()"
                     @keydown.window.x.prevent="if (event.target.tagName === 'BODY' && {{ $r->status === 'checked_in' ? 'true' : 'false' }}) $wire.checkOut()"
                     @keydown.window.p.prevent="if (event.target.tagName === 'BODY' && {{ ! in_array($r->status, ['cancelled']) ? 'true' : 'false' }}) $wire.openPaymentModal()"
                     class="hidden"></div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Left: summary --}}
        <section class="lg:col-span-2 space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid grid-cols-2 gap-y-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase text-slate-500">{{ __('Guest') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $r->leadGuest?->full_name ?? '—' }}</dd>
                        <dd class="text-xs text-slate-500">{{ $r->leadGuest?->phone }} · {{ $r->leadGuest?->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">{{ __('Room') }}</dt>
                        <dd class="font-medium">{{ $r->room?->number ?? '—' }}</dd>
                        <dd class="text-xs text-slate-500">{{ $r->room?->roomType?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">{{ __('Dates') }}</dt>
                        <dd class="font-medium">{{ $r->check_in_date->toDateString() }} → {{ $r->check_out_date->toDateString() }}</dd>
                        <dd class="text-xs text-slate-500">{{ trans_choice(':count night|:count nights', $r->nights, ['count' => $r->nights]) }} · {{ $r->adults }} {{ __('Adults') }}{{ $r->children ? ' · '.$r->children.' '.__('Children') : '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">{{ __('Status') }}</dt>
                        <dd><x-status-pill :value="$r->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">{{ __('Payment') }}</dt>
                        <dd><x-status-pill :value="$r->payment_status" kind="payment" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-slate-500">{{ __('Source') }}</dt>
                        <dd class="font-medium">{{ __(str_replace('_', ' ', $r->source)) }}</dd>
                    </div>
                </div>
                @if ($r->special_requests)
                    <div class="mt-4 rounded bg-slate-50 p-3 text-sm text-slate-700">
                        <div class="text-xs uppercase text-slate-500 mb-1">{{ __('Special requests') }}</div>
                        {{ $r->special_requests }}
                    </div>
                @endif
            </div>

            {{-- Folio --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Folio') }}</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500">
                            <th class="pb-2">{{ __('Description') }}</th><th class="pb-2 text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($r->nightsBreakdown->sortBy('date') as $n)
                            <tr><td class="py-1.5">{{ __('Night of :date', ['date' => $n->date->toDateString()]) }}</td><td class="py-1.5 text-right">{{ number_format((float) $n->nightly_rate, 2) }}</td></tr>
                        @endforeach
                        @foreach ($r->charges as $c)
                            <tr>
                                <td class="py-1.5">
                                    <span class="text-xs uppercase text-slate-400 mr-1">[{{ __($c->type) }}]</span>
                                    {{ $c->description }}
                                </td>
                                <td class="py-1.5 text-right {{ $c->type === 'discount' ? 'text-emerald-700' : '' }}">
                                    {{ $c->type === 'discount' ? '−' : '' }}{{ number_format((float) $c->total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 text-sm">
                        <tr><td class="pt-2 text-right text-slate-500">{{ __('Room total') }}</td><td class="pt-2 text-right">{{ number_format((float) $r->room_rate_total, 2) }}</td></tr>
                        <tr><td class="text-right text-slate-500">{{ __('Extras') }}</td><td class="text-right">{{ number_format((float) $r->extras_total, 2) }}</td></tr>
                        @if ((float) $r->discount_total > 0)
                            <tr><td class="text-right text-slate-500">{{ __('Discount') }}</td><td class="text-right text-emerald-700">−{{ number_format((float) $r->discount_total, 2) }}</td></tr>
                        @endif
                        <tr class="font-semibold"><td class="pt-2 text-right">{{ __('Grand total') }}</td><td class="pt-2 text-right">{{ number_format((float) $r->grand_total, 2) }} {{ $r->currency }}</td></tr>
                        <tr><td class="text-right text-slate-500">{{ __('Paid') }}</td><td class="text-right">{{ number_format((float) $r->paid_total, 2) }} {{ $r->currency }}</td></tr>
                        <tr class="font-semibold"><td class="text-right">{{ __('Balance') }}</td><td class="text-right">{{ number_format((float) $r->grand_total - (float) $r->paid_total, 2) }} {{ $r->currency }}</td></tr>
                    </tfoot>
                </table>
            </div>

            {{-- Payments --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Payments') }}</h2>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($r->payments as $p)
                        <li class="flex justify-between py-2">
                            <div>
                                <div class="font-medium">{{ number_format((float) $p->amount, 2) }} {{ $p->currency }}</div>
                                <div class="text-xs text-slate-500">{{ __(str_replace('_', ' ', $p->method)) }} · {{ optional($p->paid_at)->format('Y-m-d H:i') }} · {{ $p->receivedBy?->name ?? '—' }}</div>
                            </div>
                            <x-status-pill :value="$p->status" kind="payment" />
                        </li>
                    @empty
                        <li class="py-4 text-center text-slate-400">{{ __('No payments yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </section>

        {{-- Right: status history + at-a-glance info --}}
        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">{{ __('At a glance') }}</h2>
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Code') }}</dt><dd class="font-medium">{{ $r->code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Created') }}</dt><dd>{{ optional($r->created_at)->format('Y-m-d') }}</dd></div>
                    @if ($r->checked_in_at)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Checked in') }}</dt><dd>{{ $r->checked_in_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($r->checked_out_at)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Checked out') }}</dt><dd>{{ $r->checked_out_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    @if ($r->cancelled_at)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Cancelled') }}</dt><dd>{{ $r->cancelled_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Balance') }}</dt><dd class="font-semibold">{{ number_format((float) $r->grand_total - (float) $r->paid_total, 2) }} {{ $r->currency }}</dd></div>
                </dl>
            </div>

            @if ($r->internal_notes)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-amber-900 mb-2">{{ __('Internal notes') }}</h2>
                    <p class="text-sm text-amber-900 whitespace-pre-line">{{ $r->internal_notes }}</p>
                </div>
            @endif

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Status history') }}</h2>
                <ol class="relative border-s border-slate-200 ps-4 text-sm">
                    @foreach ($r->statusHistory->sortBy('changed_at') as $h)
                        @php
                            $from = __(str_replace('_', ' ', (string) $h->from_status));
                            $to   = __(str_replace('_', ' ', (string) $h->to_status));
                        @endphp
                        <li class="mb-3 ms-2">
                            <div class="absolute -ms-[7px] mt-1 h-2.5 w-2.5 rounded-full bg-slate-400"></div>
                            <div class="font-medium">{{ $h->from_status ? "$from → $to" : "→ $to" }}</div>
                            <div class="text-xs text-slate-500">{{ optional($h->changed_at)->format('Y-m-d H:i') }} · {{ $h->changedBy?->name ?? '—' }}</div>
                            @if ($h->note)
                                <div class="text-xs text-slate-600 mt-0.5">{{ __($h->note) }}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </div>

    {{-- Payment modal --}}
    <div x-cloak x-show="$wire.showPaymentModal"
         @keydown.window.escape="$wire.showPaymentModal = false"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div x-show="$wire.showPaymentModal"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-init="$watch('$wire.showPaymentModal', v => { if (v) setTimeout(() => $refs.payAmount.focus(), 100) })"
             @click.outside="$wire.showPaymentModal = false"
             class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Record payment') }}</h3>
                <span class="text-xs text-slate-400">{{ __('Esc to close') }}</span>
            </div>
            <div class="mt-4 space-y-3 text-sm">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Method') }}</label>
                    <select wire:model="payMethod" class="mt-1 w-full rounded-md border-slate-300 focus:border-slate-500 focus:ring-slate-500">
                        @foreach (\App\Models\Payment::METHODS as $m)
                            <option value="{{ $m }}">{{ __(str_replace('_', ' ', $m)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Amount') }} ({{ $r->currency }})</label>
                    <div class="relative mt-1">
                        <input type="number" step="0.01" wire:model="payAmount" x-ref="payAmount"
                               @keydown.enter.prevent="$wire.recordPayment()"
                               class="w-full rounded-md border-slate-300 pr-14 focus:border-slate-500 focus:ring-slate-500">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $r->currency }}</span>
                    </div>
                    @error('payAmount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Reference (optional)') }}</label>
                    <input wire:model="payReference" placeholder="{{ __('Card last 4, transfer id…') }}"
                           class="mt-1 w-full rounded-md border-slate-300 focus:border-slate-500 focus:ring-slate-500">
                </div>
            </div>
            <div class="mt-5 flex items-center justify-between gap-2">
                <span class="text-xs text-slate-500">
                    {{ __('Balance now:') }} <span class="font-medium text-slate-700">{{ number_format((float) $r->grand_total - (float) $r->paid_total, 2) }} {{ $r->currency }}</span>
                </span>
                <div class="flex gap-2">
                    <button wire:click="$set('showPaymentModal', false)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Cancel') }}</button>
                    <button wire:click="recordPayment"
                            wire:loading.attr="disabled" wire:target="recordPayment"
                            class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                        <x-spinner wire:loading wire:target="recordPayment" class="h-4 w-4 -ml-1" />
                        {{ __('Record') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Charge modal --}}
    <div x-cloak x-show="$wire.showChargeModal"
         @keydown.window.escape="$wire.showChargeModal = false"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div x-show="$wire.showChargeModal"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-init="$watch('$wire.showChargeModal', v => { if (v) setTimeout(() => $refs.chargeDesc.focus(), 100) })"
             @click.outside="$wire.showChargeModal = false"
             class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Add charge') }}</h3>
                <span class="text-xs text-slate-400">{{ __('Esc to close') }}</span>
            </div>
            <div class="mt-4 space-y-3 text-sm">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Type') }}</label>
                    <select wire:model="chargeType" class="mt-1 w-full rounded-md border-slate-300">
                        @foreach (\App\Models\ReservationCharge::TYPES as $t)
                            <option value="{{ $t }}">{{ __($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
                    <input wire:model="chargeDescription" x-ref="chargeDesc"
                           class="mt-1 w-full rounded-md border-slate-300 focus:border-slate-500 focus:ring-slate-500">
                    @error('chargeDescription') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Amount') }} ({{ $r->currency }})</label>
                    <input type="number" step="0.01" wire:model="chargeAmount"
                           @keydown.enter.prevent="$wire.addCharge()"
                           class="mt-1 w-full rounded-md border-slate-300 focus:border-slate-500 focus:ring-slate-500">
                    @error('chargeAmount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button wire:click="$set('showChargeModal', false)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Cancel') }}</button>
                <button wire:click="addCharge"
                        wire:loading.attr="disabled" wire:target="addCharge"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                    <x-spinner wire:loading wire:target="addCharge" class="h-4 w-4 -ml-1" />
                    {{ __('Add') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Cancel modal --}}
    <div x-cloak x-show="$wire.showCancelModal"
         @keydown.window.escape="$wire.showCancelModal = false"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div x-show="$wire.showCancelModal"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-init="$watch('$wire.showCancelModal', v => { if (v) setTimeout(() => $refs.cancelReason.focus(), 100) })"
             @click.outside="$wire.showCancelModal = false"
             class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-900">{{ __('Cancel reservation?') }}</h3>
            <p class="mt-2 text-sm text-slate-600">{{ __("This releases the room. You can't undo it.") }}</p>
            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">{{ __('Reason') }}</label>
                <textarea wire:model="cancelReason" rows="3" x-ref="cancelReason"
                          class="mt-1 w-full rounded-md border-slate-300 focus:border-slate-500 focus:ring-slate-500"></textarea>
                @error('cancelReason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button wire:click="$set('showCancelModal', false)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Keep reservation') }}</button>
                <button wire:click="confirmCancel"
                        wire:loading.attr="disabled" wire:target="confirmCancel"
                        class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                    <x-spinner wire:loading wire:target="confirmCancel" class="h-4 w-4 -ml-1" />
                    {{ __('Cancel reservation') }}
                </button>
            </div>
        </div>
    </div>
</div>
