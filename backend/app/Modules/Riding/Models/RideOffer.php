<?php

declare(strict_types=1);

namespace App\Modules\Riding\Models;

use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ride_id
 * @property int $driver_id
 * @property Carbon $offered_at
 * @property Carbon $expires_at
 * @property string $response
 * @property Carbon|null $responded_at
 * @property int $distance_to_pickup_m
 * @property int $eta_seconds
 * @property Ride $ride
 */
class RideOffer extends Model
{
    protected $table = 'ride_offers';

    protected $fillable = [
        'ride_id',
        'driver_id',
        'offered_at',
        'expires_at',
        'response',
        'responded_at',
        'distance_to_pickup_m',
        'eta_seconds',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
