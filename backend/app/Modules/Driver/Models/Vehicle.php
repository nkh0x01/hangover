<?php

declare(strict_types=1);

namespace App\Modules\Driver\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'vehicles';

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }

    protected $fillable = [
        'driver_id',
        'type',
        'brand',
        'model',
        'plate',
        'color',
        'year',
        'vin',
        'is_active',
        'photos',
        'telemetry_supported',
        'verified_at',
        'verified_by_user_id',
        'verification_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'telemetry_supported' => 'boolean',
            'photos' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
