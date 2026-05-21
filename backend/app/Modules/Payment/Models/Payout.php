<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $table = 'payouts';

    protected $fillable = [
        'driver_id',
        'amount',
        'currency',
        'period_start',
        'period_end',
        'status',
        'provider',
        'provider_payout_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
