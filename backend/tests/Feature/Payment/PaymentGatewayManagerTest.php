<?php

declare(strict_types=1);

use App\Modules\Payment\Gateways\CashPaymentGateway;
use App\Modules\Payment\Gateways\NullPaymentGateway;
use App\Modules\Payment\Services\PaymentGatewayManager;

it('resolves cash for the cash method', function (): void {
    config()->set('payment.methods.cash', 'cash');

    $gateway = app(PaymentGatewayManager::class)->forMethod('cash');

    expect($gateway)->toBeInstanceOf(CashPaymentGateway::class);
});

it('routes card to whatever the config says — null in CI', function (): void {
    config()->set('payment.methods.card', 'null');

    $gateway = app(PaymentGatewayManager::class)->forMethod('card');

    expect($gateway)->toBeInstanceOf(NullPaymentGateway::class);
});

it('throws when asked for an unknown gateway', function (): void {
    expect(fn () => app(PaymentGatewayManager::class)->driver('nonsense'))
        ->toThrow(InvalidArgumentException::class);
});

it('caches resolved gateway instances', function (): void {
    $mgr = app(PaymentGatewayManager::class);

    $a = $mgr->driver('cash');
    $b = $mgr->driver('cash');

    expect($a)->toBe($b);
});

it('reports failure cleanly when stripe is unconfigured', function (): void {
    config()->set('payment.gateways.stripe.secret_key', '');

    expect(fn () => app(PaymentGatewayManager::class)->driver('stripe')->authorize(100, 'GEL', 'tok', 'ride'))
        ->toThrow(RuntimeException::class);
});
