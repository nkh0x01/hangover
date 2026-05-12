<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Room extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\RoomFactory> */
    use Auditable, HasFactory;

    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_OCCUPIED    = 'occupied';
    public const STATUS_DIRTY       = 'dirty';
    public const STATUS_CLEAN       = 'clean';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_BLOCKED     = 'blocked';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_OCCUPIED,
        self::STATUS_DIRTY,
        self::STATUS_CLEAN,
        self::STATUS_MAINTENANCE,
        self::STATUS_BLOCKED,
    ];

    protected $fillable = [
        'property_id',
        'room_type_id',
        'number',
        'floor',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function availability(): HasMany
    {
        return $this->hasMany(AvailabilityCalendar::class);
    }

    public function nights(): HasMany
    {
        return $this->hasMany(ReservationNight::class);
    }
}
