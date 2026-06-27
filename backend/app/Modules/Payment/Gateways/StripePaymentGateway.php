<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\GatewayResult;
use App\Modules\Payment\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stripe placeholder.
 *
 * Phase 2.3 ships the structural shape only — the SDK call is gated
 * behind {@see assertConfigured()} so an accidental "use Stripe for
 * card payments" flip in `config/payment.php` without a `secret_key`
 * fails loudly rather than producing a half-successful flow.
 *
 * When credentials are available:
 *   composer require stripe/stripe-php
 * then replace the marked TODO blocks below with the real SDK calls.
 *
 * Reference shape (PaymentIntent + capture-on-completion):
 *   $stripe = new \Stripe\StripeClient($secret);
 *   $intent = $stripe->paymentIntents->create([
 *       'amount' => $amountMinor,
 *       'currency' => strtolower($currency),
 *       'payment_method' => $methodToken,
 *       'confirm' => true,
 *       'capture_method' => 'manual',
 *       'off_session' => true,
 *       'metadata' => ['ride_ulid' => $rideUlid],
 *   ]);
 *
 * Webhooks for `payment_intent.succeeded` / `payment_intent.payment_failed`
 * will be handled by `App\Modules\Payment\Http\Webhooks\StripeWebhookController`
 * (created in Phase 2.4 once we're ready to enable card).
 */
final class StripePaymentGateway implements PaymentGateway
{
    public function authorize(int $amountMinor, string $currency, string $methodToken, string $rideUlid): GatewayResult
    {
        $this->assertConfigured();

        Log::channel('payment')->warning('Stripe authorize called without SDK', [
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'ride_ulid' => $rideUlid,
        ]);

        // TODO(payments): wire stripe/stripe-php once enabled.
        return new GatewayResult(
            ok: false,
            status: 'failed',
            providerIntentId: null,
            failureCode: 'GATEWAY_NOT_WIRED',
        );
    }

    public function capture(string $providerIntentId): GatewayResult
    {
        $this->assertConfigured();

        // TODO(payments): $stripe->paymentIntents->capture($providerIntentId);
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

        // TODO(payments): $stripe->refunds->create(['payment_intent' => $providerIntentId, 'amount' => $amountMinor]);
        return new GatewayResult(
            ok: false,
            status: 'failed',
            providerIntentId: $providerIntentId,
            failureCode: 'GATEWAY_NOT_WIRED',
        );
    }

    private function assertConfigured(): void
    {
        $secret = (string) config('payment.gateways.stripe.secret_key');
        if ($secret === '') {
            throw new RuntimeException(
                'StripePaymentGateway: STRIPE_SECRET_KEY is not configured. '
                .'Either provide it or route `card` to a different gateway in config/payment.php.',
            );
        }
    }
}
