<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Transaction;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletPoster;
use App\Support\Money;

it('creates a wallet on first post and credits it correctly', function (): void {
    $user = User::factory()->create();

    $tx = app(WalletPoster::class)->post(
        $user,
        Money::fromDecimal('17.00', 'GEL'),
        ['kind' => 'ride_payout', 'direction' => 'credit', 'description' => 't1'],
    );

    expect($tx->balance_after)->toBe('17.00');
    expect($tx->direction)->toBe('credit');

    $wallet = Wallet::query()->where('user_id', $user->id)->first();
    expect((float) $wallet->balance_cached)->toBe(17.00);
});

it('debits + maintains a running balance across many posts', function (): void {
    $user = User::factory()->create();
    $poster = app(WalletPoster::class);

    $poster->post($user, Money::fromDecimal('20.00', 'GEL'), ['kind' => 'topup', 'direction' => 'credit']);
    $poster->post($user, Money::fromDecimal('5.00', 'GEL'), ['kind' => 'ride_charge', 'direction' => 'debit']);
    $poster->post($user, Money::fromDecimal('3.00', 'GEL'), ['kind' => 'ride_charge', 'direction' => 'debit']);

    $wallet = Wallet::query()->where('user_id', $user->id)->first();
    expect((float) $wallet->balance_cached)->toBe(12.00);

    $tx = Transaction::query()->where('wallet_id', $wallet->id)->latest('id')->first();
    expect((float) $tx->balance_after)->toBe(12.00);
});

it('is idempotent when the same idempotency_key is reused', function (): void {
    $user = User::factory()->create();
    $poster = app(WalletPoster::class);

    $first = $poster->post(
        $user,
        Money::fromDecimal('10.00', 'GEL'),
        [
            'kind' => 'refund',
            'direction' => 'credit',
            'meta' => ['idempotency_key' => 'refund:42'],
        ],
    );

    $second = $poster->post(
        $user,
        Money::fromDecimal('10.00', 'GEL'),
        [
            'kind' => 'refund',
            'direction' => 'credit',
            'meta' => ['idempotency_key' => 'refund:42'],
        ],
    );

    expect($second->id)->toBe($first->id);
    expect(Transaction::query()->where('wallet_id', $first->wallet_id)->count())->toBe(1);
});

it('refuses zero or negative amounts', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(WalletPoster::class)->post(
        $user,
        new Money(0, 'GEL'),
        ['kind' => 'topup', 'direction' => 'credit'],
    ))->toThrow(InvalidArgumentException::class);
});

it('refuses an unknown direction', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(WalletPoster::class)->post(
        $user,
        Money::fromDecimal('5.00', 'GEL'),
        ['kind' => 'topup', 'direction' => 'sideways'],
    ))->toThrow(InvalidArgumentException::class);
});

it('tracks holds separately from balance', function (): void {
    $user = User::factory()->create();
    $poster = app(WalletPoster::class);

    $poster->post($user, Money::fromDecimal('20.00', 'GEL'), ['kind' => 'topup', 'direction' => 'credit']);
    $poster->hold($user, Money::fromDecimal('7.00', 'GEL'), 'pre-auth');

    $wallet = Wallet::query()->where('user_id', $user->id)->first();
    expect((float) $wallet->balance_cached)->toBe(20.00);
    expect((float) $wallet->held_amount)->toBe(7.00);

    $poster->release($user, Money::fromDecimal('7.00', 'GEL'), 'release');

    $wallet = $wallet->refresh();
    expect((float) $wallet->held_amount)->toBe(0.00);
});
