<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Identity\Models\User;
use App\Support\Ulid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $customer_id
 * @property int $city_id
 * @property float $pickup_lat
 * @property float $pickup_lng
 * @property float $dropoff_lat
 * @property float $dropoff_lng
 * @property string $distance_km
 * @property int $duration_min
 * @property string $base_fare
 * @property string $surge_multiplier
 * @property int|null $promo_code_id
 * @property string $total_amount
 * @property string $currency
 * @property Carbon $expires_at
 */
class FareEstimate extends Model
{
    protected $table = 'fare_estimates';

    protected $fillable = [
        'ulid',
        'customer_id',
        'city_id',
        'pickup_lat',
        'pickup_lng',
        'dropoff_lat',
        'dropoff_lng',
        'distance_km',
        'duration_min',
        'base_fare',
        'surge_multiplier',
        'promo_code_id',
        'total_amount',
        'currency',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dropoff_lat' => 'float',
            'dropoff_lng' => 'float',
            'distance_km' => 'decimal:3',
            'duration_min' => 'integer',
            'base_fare' => 'decimal:2',
            'surge_multiplier' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->ulid ??= Ulid::new();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
