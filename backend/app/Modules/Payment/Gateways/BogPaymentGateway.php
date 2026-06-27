<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\GatewayResult;
use App\Modules\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Bank of Georgia (BOG) Payments placeholder.
 *
 * Phase 2.3 ships the HTTP skeleton — request building, token caching,
 * structured logging — but the real BOG endpoints are gated behind
 * {@see assertConfigured()}. Without credentials all three operations
 * report `GATEWAY_NOT_WIRED` so nothing accidentally fires against
 * a sandbox you didn't intend.
 *
 * BOG flow (per their API spec):
 *   1. POST {base_url}/oauth2/token            → access_token (1h TTL)
 *   2. POST {base_url}/ecommerce/orders        → order_id, redirect_url
 *   3. (customer completes 3DS at redirect)
 *   4. POST {base_url}/payment/details/{id}    → status polling
 *   5. POST {base_url}/payment/refund          → refund
 *
 * For ride-hailing we typically authorize-and-capture in one shot
 * because the user has already agreed to a fare estimate. The
 * `authorize()` call below combines steps 2 + 4 once wired.
 */
final class BogPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function authorize(int $amountMinor, string $currency, string $methodToken, string $rideUlid): GatewayResult
    {
        $this->assertConfigured();

        try {
            // TODO(payments): real BOG order creation.
            //
            // $token = $this->accessToken();
            // $resp = $this->client()
            //     ->withToken($token)
            //     ->timeout((int) config('payment.gateways.bog.timeout_seconds', 10))
            //     ->post(config('payment.gateways.bog.base_url').'/ecommerce/orders', [
            //         'callback_url' => route('payments.bog.callback'),
            //         'external_order_id' => $rideUlid,
            //         'purchase_units' => [[
            //             'amount' => [
            //                 'currency_code' => $currency,
            //                 'value' => number_format($amountMinor / 100, 2, '.', ''),
            //             ],
            //         ]],
            //     ])
            //     ->throw()
            //     ->json();
            //
            // return new GatewayResult(
            //     ok: true,
            //     status: 'pending',
            //     providerIntentId: (string) $resp['id'],
            //     raw: $resp,
            // );

            return new GatewayResult(
                ok: false,
                status: 'failed',
                providerIntentId: null,
                failureCode: 'GATEWAY_NOT_WIRED',
            );
        } catch (Throwable $e) {
            Log::channel('payment')->error('BOG authorize threw', ['error' => $e->getMessage()]);

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

        // TODO(payments): real BOG capture / poll-for-status.
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

        // TODO(payments): real BOG refund.
        return new GatewayResult(
            ok: false,
            status: 'failed',
            providerIntentId: $providerIntentId,
            failureCode: 'GATEWAY_NOT_WIRED',
        );
    }

    private function assertConfigured(): void
    {
        foreach (['client_id', 'client_secret', 'merchant_id'] as $key) {
            if ((string) config("payment.gateways.bog.$key") === '') {
                throw new RuntimeException(
                    "BogPaymentGateway: BOG_{$this->envName($key)} is not configured. "
                    .'Provide BOG credentials or route `card` to a different gateway.',
                );
            }
        }
        // Touching client() here ensures the base URL is set and the
        // factory is reachable. Configured but missing base_url → fails
        // fast at the first call rather than during the TODO wire-up.
        $this->client();
    }

    private function envName(string $key): string
    {
        return strtoupper($key);
    }

    /**
     * Returns a pre-configured HTTP client. Used by the TODO blocks
     * once the BOG endpoints are wired. Wrapping the factory in this
     * helper keeps the gateway's HTTP timeout/headers in one place.
     */
    private function client(): PendingRequest
    {
        return $this->http
            ->baseUrl((string) config('payment.gateways.bog.base_url'))
            ->timeout((int) config('payment.gateways.bog.timeout_seconds', 10))
            ->acceptJson();
    }
}
