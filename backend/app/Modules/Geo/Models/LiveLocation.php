<?php

declare(strict_types=1);

namespace App\Modules\Geo\Models;

use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveLocation extends Model
{
    protected $table = 'live_locations';

    public $timestamps = false;

    protected $fillable = [
        'driver_id',
        'ride_id',
        'recorded_at',
        'heading',
        'speed_kmh',
        'accuracy_m',
        'battery_pct',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'speed_kmh' => 'decimal:2',
            'accuracy_m' => 'decimal:1',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
