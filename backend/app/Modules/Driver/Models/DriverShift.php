<?php

declare(strict_types=1);

namespace App\Modules\Driver\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverShift extends Model
{
    protected $table = 'driver_shifts';

    protected $fillable = [
        'driver_id',
        'started_at',
        'ended_at',
        'started_lat',
        'started_lng',
        'ended_lat',
        'ended_lng',
        'total_distance_km',
        'total_earnings',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
