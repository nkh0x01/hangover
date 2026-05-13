<div>
    <x-slot name="header">{{ __('Invoice :number', ['number' => $invoice->number]) }}</x-slot>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('reservations.show', $invoice->reservation) }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('← Reservation') }}</a>
        <button onclick="window.print()"
                class="ml-auto rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">{{ __('Print') }}</button>
    </div>

    <article class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $invoice->property->name }}</h1>
                <p class="text-sm text-slate-500">{{ $invoice->property->address['line1'] ?? '' }}, {{ $invoice->property->address['city'] ?? '' }}</p>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase text-slate-500">{{ __('Invoice') }}</div>
                <div class="text-xl font-semibold text-slate-900">{{ $invoice->number }}</div>
                <div class="text-xs text-slate-500">{{ optional($invoice->issued_at)->format('Y-m-d') }}</div>
                <div class="mt-2"><x-status-pill :value="$invoice->status" /></div>
            </div>
        </header>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 text-sm">
            <div>
                <div class="text-xs uppercase text-slate-500">{{ __('Billed to') }}</div>
                <div class="font-medium text-slate-900">{{ $invoice->guest_snapshot['name'] ?? '—' }}</div>
                <div class="text-slate-500">{{ $invoice->guest_snapshot['email'] ?? '' }}</div>
                <div class="text-slate-500">{{ $invoice->guest_snapshot['phone'] ?? '' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-slate-500">{{ __('Reservation') }}</div>
                <div class="font-medium text-slate-900">{{ $invoice->reservation->code }}</div>
                <div class="text-slate-500">{{ __('Room') }} {{ $invoice->reservation->room?->number }}</div>
                <div class="text-slate-500">{{ $invoice->reservation->check_in_date->toDateString() }} → {{ $invoice->reservation->check_out_date->toDateString() }}</div>
            </div>
        </section>

        <section class="mt-8">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="pb-2">{{ __('Description') }}</th>
                        <th class="pb-2 text-right">{{ __('Qty') }}</th>
                        <th class="pb-2 text-right">{{ __('Unit price') }}</th>
                        <th class="pb-2 text-right">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($invoice->lines as $line)
                        <tr>
                            <td class="py-2">{{ $line->description }}</td>
                            <td class="py-2 text-right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                            <td class="py-2 text-right">{{ number_format((float) $line->unit_price, 2) }}</td>
                            <td class="py-2 text-right">{{ number_format((float) $line->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php
                        $vatRate = (float) ($invoice->property->vat_rate_default ?? 0);
                        $taxTotal = (float) $invoice->tax_total;
                        $showTax = $taxTotal > 0 || $vatRate > 0;
                        if ($showTax && $taxTotal == 0 && $vatRate > 0) {
                            $taxTotal = round((float) $invoice->subtotal * $vatRate / (100 + $vatRate), 2);
                        }
                    @endphp
                    <tr><td colspan="3" class="pt-3 text-right text-slate-500">{{ __('Subtotal') }}</td><td class="pt-3 text-right">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                    @if ((float) $invoice->discount_total > 0)
                        <tr><td colspan="3" class="text-right text-slate-500">{{ __('Discount') }}</td><td class="text-right text-emerald-700">−{{ number_format((float) $invoice->discount_total, 2) }}</td></tr>
                    @endif
                    @if ($showTax)
                        <tr>
                            <td colspan="3" class="text-right text-slate-500">
                                @if ($vatRate > 0)
                                    {{ __('Tax (VAT :rate%, included)', ['rate' => rtrim(rtrim(number_format($vatRate, 2), '0'), '.')]) }}
                                @else
                                    {{ __('Tax') }}
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($taxTotal, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="border-t border-slate-200 font-semibold"><td colspan="3" class="pt-2 text-right">{{ __('Total') }}</td><td class="pt-2 text-right">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td></tr>
                    <tr><td colspan="3" class="text-right text-slate-500">{{ __('Paid') }}</td><td class="text-right">{{ number_format((float) $invoice->paid_total, 2) }} {{ $invoice->currency }}</td></tr>
                    <tr class="font-semibold"><td colspan="3" class="text-right">{{ __('Balance') }}</td><td class="text-right">{{ number_format((float) $invoice->balance, 2) }} {{ $invoice->currency }}</td></tr>
                </tfoot>
            </table>
        </section>

        <footer class="mt-10 border-t border-slate-200 pt-4 text-xs text-slate-500">
            {{ __('Thank you for your stay.') }}
        </footer>
    </article>
</div>
