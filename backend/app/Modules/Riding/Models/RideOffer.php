<?php

declare(strict_types=1);

namespace App\Modules\Riding\Models;

use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
