<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ?Carbon $captured_at
 * @property string $provider
 * @property string $method
 * @property string $status
 * @property string|float $amount
 * @property string $currency
 * @property ?string $failure_code
 * @property ?string $provider_intent_id
 * @property int $ride_id
 * @property int $customer_id
 * @property ?Ride $ride
 * @property ?User $customer
 */
class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'ride_id', 'customer_id', 'provider', 'provider_intent_id', 'method',
        'amount', 'currency', 'status', 'failure_code', 'captured_at', 'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'captured_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
