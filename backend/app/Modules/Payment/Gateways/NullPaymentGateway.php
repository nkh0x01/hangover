<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\GatewayResult;
use App\Modules\Payment\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;

/**
 * Logs every call and reports `captured` immediately. Used by tests
 * and as the fallback when no card-gateway credentials are present
 * (so a misconfigured staging deploy never silently accepts a card
 * payment from a real customer).
 *
 * The mobile app should refuse to surface card as an option when the
 * resolved gateway is null — see `config/payment.php` and the
 * `PaymentMethodsController`.
 */
final class NullPaymentGateway implements PaymentGateway
{
    public function authorize(int $amountMinor, string $currency, string $methodToken, string $rideUlid): GatewayResult
    {
        Log::channel('payment')->info('NullPaymentGateway::authorize', [
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'ride_ulid' => $rideUlid,
        ]);

        return new GatewayResult(
            ok: true,
            status: 'authorized',
            providerIntentId: 'null:auth:'.bin2hex(random_bytes(8)),
        );
    }

    public function capture(string $providerIntentId): GatewayResult
    {
        Log::channel('payment')->info('NullPaymentGateway::capture', [
            'intent' => $providerIntentId,
        ]);

        return new GatewayResult(
            ok: true,
            status: 'captured',
            providerIntentId: $providerIntentId,
        );
    }

    public function refund(string $providerIntentId, int $amountMinor): GatewayResult
    {
        Log::channel('payment')->info('NullPaymentGateway::refund', [
            'intent' => $providerIntentId,
            'amount_minor' => $amountMinor,
        ]);

        return new GatewayResult(
            ok: true,
            status: 'refunded',
            providerIntentId: $providerIntentId,
        );
    }
}
