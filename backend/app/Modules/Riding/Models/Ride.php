<?php

declare(strict_types=1);

namespace App\Modules\Riding\Models;

use App\Modules\Driver\Models\Driver;
use App\Modules\Identity\Models\User;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Ulid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    protected $table = 'rides';

    protected $fillable = [
        'ulid',
        'customer_id',
        'driver_id',
        'vehicle_id',
        'city_id',
        'status',
        'cancellation_reason',
        'cancellation_by_user_id',
        'pickup_address',
        'dropoff_address',
        'fare_estimate_id',
        'quoted_amount',
        'final_amount',
        'surge_multiplier',
        'distance_km',
        'duration_seconds',
        'waiting_seconds',
        'currency',
        'payment_method',
        'payment_id',
        'promo_code_id',
        'commission_amount',
        'driver_earnings',
        'requested_at',
        'accepted_at',
        'arriving_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'customer_rating',
        'driver_rating',
    ];

    protected function casts(): array
    {
        return [
            'status' => RideStatus::class,
            'quoted_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'surge_multiplier' => 'decimal:2',
            'distance_km' => 'decimal:3',
            'commission_amount' => 'decimal:2',
            'driver_earnings' => 'decimal:2',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'arriving_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ride): void {
            $ride->ulid ??= Ulid::new();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(RideStatusLog::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RideOffer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RideMessage::class);
    }
}
