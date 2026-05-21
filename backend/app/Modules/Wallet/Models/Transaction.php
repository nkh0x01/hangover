<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use App\Support\Ulid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'ulid', 'wallet_id', 'kind', 'direction', 'amount', 'currency',
        'ride_id', 'payment_id', 'payout_id', 'balance_after', 'description', 'meta', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tx): void {
            $tx->ulid ??= Ulid::new();
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
