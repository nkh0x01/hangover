<?php

declare(strict_types=1);

namespace App\Modules\Riding\Models;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Ulid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $customer_id
 * @property int|null $driver_id
 * @property int|null $vehicle_id
 * @property int $city_id
 * @property RideStatus $status
 * @property string|null $cancellation_reason
 * @property int|null $cancellation_by_user_id
 * @property string $pickup_address
 * @property string $dropoff_address
 * @property int|null $fare_estimate_id
 * @property string $quoted_amount
 * @property string|null $final_amount
 * @property string $surge_multiplier
 * @property string|null $distance_km
 * @property int|null $duration_seconds
 * @property int|null $waiting_seconds
 * @property string $currency
 * @property string $payment_method
 * @property int|null $payment_id
 * @property int|null $promo_code_id
 * @property string|null $commission_amount
 * @property string|null $driver_earnings
 * @property Carbon|null $requested_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $arriving_at
 * @property Carbon|null $arrived_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property int|null $customer_rating
 * @property int|null $driver_rating
 */
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
        'is_test_ride',
        'pilot_cohort',
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
            'is_test_ride' => 'boolean',
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

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(RideStatusLog::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RideOffer::class);
    }

    /**
     * @return array{pickup_lat: float, pickup_lng: float, dropoff_lat: float, dropoff_lng: float}
     */
    public function mapCoordinates(): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return [
                'pickup_lat' => 0.0, 'pickup_lng' => 0.0,
                'dropoff_lat' => 0.0, 'dropoff_lng' => 0.0,
            ];
        }

        $row = DB::selectOne(
            'SELECT
                ST_Y(pickup_location)  AS pickup_lat,
                ST_X(pickup_location)  AS pickup_lng,
                ST_Y(dropoff_location) AS dropoff_lat,
                ST_X(dropoff_location) AS dropoff_lng
              FROM rides WHERE id = ?',
            [$this->id],
        );

        return [
            'pickup_lat' => (float) ($row->pickup_lat ?? 0.0),
            'pickup_lng' => (float) ($row->pickup_lng ?? 0.0),
            'dropoff_lat' => (float) ($row->dropoff_lat ?? 0.0),
            'dropoff_lng' => (float) ($row->dropoff_lng ?? 0.0),
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RideMessage::class);
    }
}
