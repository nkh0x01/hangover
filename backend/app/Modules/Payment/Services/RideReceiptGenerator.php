<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\Payment;
use App\Modules\Riding\Models\Ride;
use Illuminate\Support\Carbon;

/**
 * Generates a plain-text receipt for a completed (or cancelled-with-fee)
 * ride. Returned as a structured array so it can be rendered as JSON
 * (for the in-app receipt screen), HTML (for the customer email), or
 * fed into a PDF library in a later phase.
 *
 * We deliberately do NOT generate PDFs here — `dompdf` adds 20 MB to
 * the docker image and we don't need PDF for pilot.
 */
final class RideReceiptGenerator
{
    /**
     * @return array{
     *   ride_ulid: string,
     *   issued_at: string,
     *   customer: array{name: ?string, phone: string},
     *   driver: array{name: ?string, vehicle: ?string, plate: ?string},
     *   pickup: ?string,
     *   dropoff: ?string,
     *   timeline: array<int, array{at: ?string, status: string}>,
     *   amounts: array{
     *     fare: float,
     *     surge_multiplier: float,
     *     commission: ?float,
     *     driver_earnings: ?float,
     *     total: float,
     *     currency: string,
     *   },
     *   payment: ?array{method: string, provider: string, status: string, captured_at: ?string},
     *   refunds: array<int, array{amount: float, reason: string, status: string, at: string}>,
     * }
     */
    public function generate(Ride $ride): array
    {
        $ride->loadMissing(['customer', 'driver.user', 'driver.currentVehicle', 'statusLogs']);

        $payment = Payment::query()
            ->where('ride_id', $ride->id)
            ->whereIn('status', ['captured', 'refunded', 'partially_refunded', 'failed'])
            ->latest('id')
            ->first();

        $refunds = $payment !== null
            ? $payment->refunds()->orderBy('created_at')->get()
            : collect();

        return [
            'ride_ulid' => $ride->ulid,
            'issued_at' => now()->toIso8601String(),
            'customer' => [
                'name' => $ride->customer?->name,
                'phone' => $this->maskPhone((string) ($ride->customer->phone_e164 ?? '')),
            ],
            'driver' => [
                'name' => $ride->driver?->user?->name,
                'vehicle' => $ride->driver?->currentVehicle
                    ? trim(($ride->driver->currentVehicle->make ?? '').' '.($ride->driver->currentVehicle->model ?? ''))
                    : null,
                'plate' => $ride->driver?->currentVehicle?->plate_number,
            ],
            'pickup' => $ride->pickup_address,
            'dropoff' => $ride->dropoff_address,
            'timeline' => $ride->statusLogs->map(fn ($log): array => [
                'at' => $log->created_at?->toIso8601String(),
                'status' => (string) $log->to_status,
            ])->all(),
            'amounts' => [
                'fare' => (float) ($ride->quoted_amount ?? 0),
                'surge_multiplier' => (float) ($ride->surge_multiplier ?? 1),
                'commission' => $ride->commission_amount !== null ? (float) $ride->commission_amount : null,
                'driver_earnings' => $ride->driver_earnings !== null ? (float) $ride->driver_earnings : null,
                'total' => (float) ($ride->final_amount ?? $ride->quoted_amount ?? 0),
                'currency' => (string) $ride->currency,
            ],
            'payment' => $payment !== null ? [
                'method' => (string) $payment->method,
                'provider' => (string) $payment->provider,
                'status' => (string) $payment->status,
                'captured_at' => $payment->captured_at?->toIso8601String(),
            ] : null,
            'refunds' => $refunds->map(fn ($r): array => [
                'amount' => (float) $r->amount,
                'reason' => (string) $r->reason,
                'status' => (string) $r->status,
                'at' => Carbon::parse((string) $r->created_at)->toIso8601String(),
            ])->all(),
        ];
    }

    public function asText(Ride $ride): string
    {
        $data = $this->generate($ride);
        $lines = [];
        $lines[] = 'HANGOVER MOBILITY — RECEIPT';
        $lines[] = str_repeat('=', 36);
        $lines[] = 'Ride:    '.$data['ride_ulid'];
        $lines[] = 'Issued:  '.$data['issued_at'];
        $lines[] = '';
        $lines[] = 'Pickup:  '.($data['pickup'] ?? '-');
        $lines[] = 'Dropoff: '.($data['dropoff'] ?? '-');
        $lines[] = '';
        $lines[] = sprintf(
            'Driver:  %s (%s, %s)',
            $data['driver']['name'] ?? '-',
            $data['driver']['vehicle'] ?? '-',
            $data['driver']['plate'] ?? '-',
        );
        $lines[] = '';
        $lines[] = sprintf('Fare      : %8.2f %s', $data['amounts']['fare'], $data['amounts']['currency']);
        if ($data['amounts']['surge_multiplier'] !== 1.0) {
            $lines[] = sprintf('Surge x   : %8.2f', $data['amounts']['surge_multiplier']);
        }
        $lines[] = sprintf('TOTAL     : %8.2f %s', $data['amounts']['total'], $data['amounts']['currency']);

        if ($data['payment'] !== null) {
            $lines[] = '';
            $lines[] = sprintf('Paid via:  %s (%s)', $data['payment']['method'], $data['payment']['status']);
        }
        foreach ($data['refunds'] as $r) {
            $lines[] = sprintf('Refund:   -%8.2f %s — %s', $r['amount'], $data['amounts']['currency'], $r['reason']);
        }

        return implode("\n", $lines);
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 4) {
            return $phone;
        }

        return substr($phone, 0, 4).str_repeat('*', max(0, strlen($phone) - 7)).substr($phone, -3);
    }
}
