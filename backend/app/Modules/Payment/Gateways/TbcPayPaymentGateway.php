<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\GatewayResult;
use App\Modules\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * TBC Pay placeholder.
 *
 * Same structural pattern as {@see BogPaymentGateway}: HTTP scaffold,
 * config-gate, structured failure modes. Real endpoints + signing
 * land in Phase 2.4 alongside the credentials.
 *
 * TBC Pay flow (per their merchant docs):
 *   1. POST /v1/auth/token             → bearer (1h)
 *   2. POST /v1/payments               → payment_id, redirect_url
 *   3. (customer completes 3DS)
 *   4. GET  /v1/payments/{id}/status   → status polling
 *   5. POST /v1/payments/{id}/refund   → refund
 *
 * Note: TBC also offers "saved card" tokenisation that lets us do a
 * non-redirect MOTO charge for repeat customers. That's a Phase 2.5
 * follow-up.
 */
final class TbcPayPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function authorize(int $amountMinor, string $currency, string $methodToken, string $rideUlid): GatewayResult
    {
        $this->assertConfigured();

        try {
            // TODO(payments): real TBC Pay payment-create call.
            return new GatewayResult(
                ok: false,
                status: 'failed',
                providerIntentId: null,
                failureCode: 'GATEWAY_NOT_WIRED',
            );
        } catch (Throwable $e) {
            Log::channel('payment')->error('TBC Pay authorize threw', ['error' => $e->getMessage()]);

            return new GatewayResult(
                ok: false,
                status: 'failed',
                providerIntentId: null,
                failureCode: 'GATEWAY_ERROR',
            );
        }
    }

    public function capture(string $providerIntentId): GatewayResult
    {
        $this->assertConfigured();

        return new GatewayResult(
            ok: false,
            status: 'failed',
            providerIntentId: $providerIntentId,
            failureCode: 'GATEWAY_NOT_WIRED',
        );
    }

    public function refund(string $providerIntentId, int $amountMinor): GatewayResult
    {
        $this->assertConfigured();

        return new GatewayResult(
            ok: false,
            status: 'failed',
            providerIntentId: $providerIntentId,
            failureCode: 'GATEWAY_NOT_WIRED',
        );
    }

    private function assertConfigured(): void
    {
        foreach (['api_key', 'api_secret'] as $key) {
            if ((string) config("payment.gateways.tbc_pay.$key") === '') {
                throw new RuntimeException(
                    "TbcPayPaymentGateway: TBC_PAY_{$this->envName($key)} is not configured. "
                    .'Provide TBC Pay credentials or route `card` to a different gateway.'
                );
            }
        }
        $this->client();
    }

    private function envName(string $key): string
    {
        return strtoupper($key);
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->http
            ->baseUrl((string) config('payment.gateways.tbc_pay.base_url'))
            ->timeout((int) config('payment.gateways.tbc_pay.timeout_seconds', 10))
            ->acceptJson();
    }
}
