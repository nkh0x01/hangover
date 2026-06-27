<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Transaction;
use App\Modules\Wallet\Models\Wallet;
use App\Support\Money;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Atomic ledger writer.
 *
 * Every money-touching action in the platform routes through
 * `WalletPoster::post()`. The method:
 *
 *   1. Opens a DB transaction.
 *   2. Locks the wallet row `FOR UPDATE` (avoids two concurrent
 *      ride completions stepping on the same balance).
 *   3. Recomputes the new balance.
 *   4. Inserts the transaction row with `balance_after` for audit.
 *   5. Writes the new cached balance back to the wallet.
 *   6. Commits.
 *
 * The single-row invariant for `transactions` is:
 *
 *     direction = credit → balance_after = balance_before + amount
 *     direction = debit  → balance_after = balance_before - amount
 *
 * Holds are NOT separate — they update `held_amount` on the wallet
 * but don't move the cached balance. They're released back via
 * `release()` (or captured via `releaseAsDebit()`).
 *
 * Idempotency: the caller is expected to provide an `idempotency_key`
 * in the meta map for any operation that could be retried (gateway
 * webhook, payout settlement, etc.). The poster looks up the latest
 * transaction with the same key + wallet and short-circuits with the
 * existing row if found.
 */
final class WalletPoster
{
    /**
     * @param  array{
     *   kind: string,
     *   direction: 'credit'|'debit',
     *   ride_id?: int|null,
     *   payment_id?: int|null,
     *   payout_id?: int|null,
     *   description?: string|null,
     *   meta?: array<string, mixed>|null,
     * }  $opts
     */
    public function post(User $user, Money $amount, array $opts): Transaction
    {
        $direction = $opts['direction'] ?? null;
        if (! in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException('direction must be credit or debit');
        }
        if ($amount->minor <= 0) {
            throw new InvalidArgumentException('amount must be > 0');
        }

        return DB::transaction(function () use ($user, $amount, $opts, $direction): Transaction {
            $wallet = $this->lockOrCreate($user, $amount->currency);

            // Idempotency short-circuit.
            $idempotencyKey = $opts['meta']['idempotency_key'] ?? null;
            if (is_string($idempotencyKey) && $idempotencyKey !== '') {
                $existing = Transaction::query()
                    ->where('wallet_id', $wallet->id)
                    ->whereJsonContains('meta->idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    Log::channel('payment')->info('WalletPoster idempotency hit', [
                        'wallet_id' => $wallet->id,
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    return $existing;
                }
            }

            $beforeMinor = $this->minorOf($wallet->balance_cached);
            $afterMinor = $direction === 'credit'
                ? $beforeMinor + $amount->minor
                : $beforeMinor - $amount->minor;

            $tx = Transaction::create([
                'wallet_id' => $wallet->id,
                'kind' => $opts['kind'],
                'direction' => $direction,
                'amount' => $amount->toDecimal(),
                'currency' => $amount->currency,
                'ride_id' => $opts['ride_id'] ?? null,
                'payment_id' => $opts['payment_id'] ?? null,
                'payout_id' => $opts['payout_id'] ?? null,
                'balance_after' => $afterMinor / 100,
                'description' => $opts['description'] ?? null,
                'meta' => $opts['meta'] ?? null,
                'occurred_at' => now(),
            ]);

            $wallet->balance_cached = $afterMinor / 100;
            $wallet->save();

            return $tx;
        });
    }

    /**
     * Place a hold on the wallet for `amount`. Returns the (no-op)
     * transaction row used for traceability.
     */
    public function hold(User $user, Money $amount, ?string $description = null, array $meta = []): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $meta): Transaction {
            $wallet = $this->lockOrCreate($user, $amount->currency);
            $wallet->held_amount = $wallet->held_amount + $amount->toDecimal();
            $wallet->save();

            return Transaction::create([
                'wallet_id' => $wallet->id,
                'kind' => 'hold',
                'direction' => 'debit',
                'amount' => $amount->toDecimal(),
                'currency' => $amount->currency,
                'balance_after' => $wallet->balance_cached,
                'description' => $description,
                'meta' => $meta,
                'occurred_at' => now(),
            ]);
        });
    }

    public function release(User $user, Money $amount, ?string $description = null, array $meta = []): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $meta): Transaction {
            $wallet = $this->lockOrCreate($user, $amount->currency);
            $wallet->held_amount = max(0, $wallet->held_amount - $amount->toDecimal());
            $wallet->save();

            return Transaction::create([
                'wallet_id' => $wallet->id,
                'kind' => 'release',
                'direction' => 'credit',
                'amount' => $amount->toDecimal(),
                'currency' => $amount->currency,
                'balance_after' => $wallet->balance_cached,
                'description' => $description,
                'meta' => $meta,
                'occurred_at' => now(),
            ]);
        });
    }

    public function walletFor(User $user, string $currency): Wallet
    {
        try {
            return Wallet::query()->where('user_id', $user->id)->firstOrFail();
        } catch (ModelNotFoundException) {
            return Wallet::create([
                'user_id' => $user->id,
                'currency' => $currency,
                'balance_cached' => 0,
                'held_amount' => 0,
            ]);
        }
    }

    private function lockOrCreate(User $user, string $currency): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if ($wallet === null) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'currency' => $currency,
                'balance_cached' => 0,
                'held_amount' => 0,
            ]);
            // Re-lock for consistency.
            $wallet = Wallet::query()->where('id', $wallet->id)->lockForUpdate()->first();
        }

        if ($wallet->currency !== $currency) {
            throw new InvalidArgumentException(
                "WalletPoster: wallet currency mismatch (have {$wallet->currency}, got {$currency}).",
            );
        }

        return $wallet;
    }

    private function minorOf(int|float|string|null $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }
}
