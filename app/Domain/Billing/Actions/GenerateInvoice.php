<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Support\InvoiceNumberGenerator;
use App\Domain\Reservations\Support\ReservationTotals;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use Illuminate\Support\Facades\DB;

class GenerateInvoice
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly ReservationTotals $totals,
    ) {
    }

    /**
     * Build (or update) the invoice for a reservation. Snapshots nightly
     * rates and charges into invoice_lines so the historical document
     * is preserved even if charges are amended later.
     */
    public function execute(Reservation $reservation): Invoice
    {
        return DB::transaction(function () use ($reservation): Invoice {
            $this->totals->recompute($reservation);
            $reservation->load(['nightsBreakdown', 'charges', 'leadGuest', 'property']);

            $invoice = $reservation->invoice()->first();
            if (! $invoice) {
                $invoice = Invoice::create([
                    'property_id'    => $reservation->property_id,
                    'number'         => $this->numbers->next($reservation->property),
                    'reservation_id' => $reservation->id,
                    'issued_at'      => now(),
                    'currency'       => $reservation->currency,
                    'status'         => Invoice::STATUS_DRAFT,
                ]);
            }

            // Wipe and re-build line items idempotently.
            $invoice->lines()->delete();

            $lines = [];

            // 1) One line per night.
            foreach ($reservation->nightsBreakdown as $night) {
                $lines[] = [
                    'invoice_id'  => $invoice->id,
                    'description' => sprintf(
                        'Room %s — night of %s',
                        $reservation->room?->number ?? '—',
                        $night->date->toDateString(),
                    ),
                    'quantity'    => 1,
                    'unit_price'  => (float) $night->nightly_rate,
                    'total'       => (float) $night->nightly_rate,
                    'tax_rate'    => 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            // 2) Extras (products and fees).
            foreach ($reservation->charges as $charge) {
                if (! in_array($charge->type, [
                    ReservationCharge::TYPE_PRODUCT,
                    ReservationCharge::TYPE_FEE,
                ], true)) {
                    continue;
                }
                $lines[] = [
                    'invoice_id'  => $invoice->id,
                    'description' => $charge->description,
                    'quantity'    => (float) $charge->quantity,
                    'unit_price'  => (float) $charge->unit_price,
                    'total'       => (float) $charge->total,
                    'tax_rate'    => (float) $charge->tax_rate,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            // 3) Discount as a negative line for clarity.
            $discountTotal = (float) $reservation->discount_total;
            if ($discountTotal > 0) {
                $lines[] = [
                    'invoice_id'  => $invoice->id,
                    'description' => 'Discount',
                    'quantity'    => 1,
                    'unit_price'  => -$discountTotal,
                    'total'       => -$discountTotal,
                    'tax_rate'    => 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            // 4) Taxes as a separate line if any.
            $taxTotal = (float) $reservation->taxes_total;
            if ($taxTotal > 0) {
                $lines[] = [
                    'invoice_id'  => $invoice->id,
                    'description' => 'Taxes',
                    'quantity'    => 1,
                    'unit_price'  => $taxTotal,
                    'total'       => $taxTotal,
                    'tax_rate'    => 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            InvoiceLine::query()->insert($lines);

            $subtotal = (float) $reservation->room_rate_total + (float) $reservation->extras_total;
            $total    = (float) $reservation->grand_total;
            $paid     = (float) $reservation->paid_total;

            $invoice->fill([
                'subtotal'       => round($subtotal, 2),
                'tax_total'      => round($taxTotal, 2),
                'discount_total' => round($discountTotal, 2),
                'total'          => round($total, 2),
                'paid_total'     => round($paid, 2),
                'balance'        => round($total - $paid, 2),
                'guest_snapshot' => [
                    'name'    => $reservation->leadGuest?->full_name,
                    'email'   => $reservation->leadGuest?->email,
                    'phone'   => $reservation->leadGuest?->phone,
                    'country' => $reservation->leadGuest?->country,
                ],
                'status'         => $paid + 0.005 >= $total
                    ? Invoice::STATUS_PAID
                    : Invoice::STATUS_ISSUED,
            ])->save();

            return $invoice->fresh('lines');
        });
    }
}
