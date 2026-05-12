<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Model;

class FareRule extends Model
{
    protected $table = 'fare_rules';

    protected $fillable = [
        'city_id', 'vehicle_type', 'name', 'base_fare', 'price_per_km', 'price_per_min',
        'minimum_fare', 'booking_fee', 'commission_rate', 'free_waiting_minutes',
        'waiting_fee_per_min', 'cancellation_fee', 'active_from', 'active_until',
        'day_of_week_mask', 'starts_at_local', 'ends_at_local',
    ];

    protected function casts(): array
    {
        return [
            'active_from' => 'datetime',
            'active_until' => 'datetime',
            'commission_rate' => 'decimal:4',
        ];
    }
}
