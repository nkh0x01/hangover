<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\GatewayResult;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Wallet\Actions\IssueWalletCredit;

/**
 * Cash settlement.
 *
 * No external call — the driver collects cash from the rider at trip
 * end. `authorize` is a no-op that immediately reports `captured`;
 * `capture` is idempotent and reports the same status; `refund` is
 * a marker only (the actual cash refund happens via wallet credit,
 * see {@see IssueWalletCredit}).
 *
 * Used by the pilot and remains a permanent option for cash-preferring
 * markets in production.
 */
final class CashPaymentGateway implements PaymentGateway
{
    public function authorize(int $amountMinor, string $currency, string $methodToken, string $rideUlid): GatewayResult
    {
        return new GatewayResult(
            ok: true,
            status: 'captured',
            providerIntentId: 'cash:'.$rideUlid,
            raw: ['amount_minor' => $amountMinor, 'currency' => $currency],
        );
    }

    public function capture(string $providerIntentId): GatewayResult
    {
        return new GatewayResult(
            ok: true,
            status: 'captured',
            providerIntentId: $providerIntentId,
        );
    }

    public function refund(string $providerIntentId, int $amountMinor): GatewayResult
    {
        // Cash refunds are tracked via wallet credits — the gateway
        // just records that the operator agreed to the refund.
        return new GatewayResult(
            ok: true,
            status: 'refunded',
            providerIntentId: $providerIntentId,
            raw: ['amount_minor' => $amountMinor],
        );
    }
}
