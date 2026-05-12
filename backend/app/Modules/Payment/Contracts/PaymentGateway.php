<?php

declare(strict_types=1);

namespace App\Modules\Payment\Contracts;

interface PaymentGateway
{
    public function authorize(int $amountMinor, string $currency, string $methodToken, string $rideUlid): GatewayResult;

    public function capture(string $providerIntentId): GatewayResult;

    public function refund(string $providerIntentId, int $amountMinor): GatewayResult;
}
