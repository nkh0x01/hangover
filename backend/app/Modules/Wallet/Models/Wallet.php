<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $table = 'wallets';

    protected $fillable = ['user_id', 'currency', 'balance_cached', 'held_amount'];

    protected function casts(): array
    {
        return [
            'balance_cached' => 'decimal:2',
            'held_amount' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
