<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Actions\SettleRidePayment;
use App\Modules\Payment\Contracts\GatewayResult;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Wallet\Services\WalletPoster;

/**
 * Wallet-credit settlement.
 *
 * Doesn't talk to anything external. The actual debit + ledger entry
 * happens inside {@see WalletPoster}
 * during {@see SettleRidePayment}.
 *
 * The gateway exists so the wallet shows up as a first-class option
 * in `config('payment.gateways')` and `payments.provider`.
 */
final class WalletPaymentGateway implements PaymentGateway
{
    public function authorize(int $amountMinor, string $currency, string $methodToken, string $rideUlid): GatewayResult
    {
        return new GatewayResult(
            ok: true,
            status: 'captured',
            providerIntentId: 'wallet:'.$rideUlid,
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
        return new GatewayResult(
            ok: true,
            status: 'refunded',
            providerIntentId: $providerIntentId,
            raw: ['amount_minor' => $amountMinor],
        );
    }
}
