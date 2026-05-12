<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityCalendar extends Model
{
    /** @use HasFactory<\Database\Factories\AvailabilityCalendarFactory> */
    use HasFactory;

    protected $table = 'availability_calendar';

    public const STATUS_OPEN        = 'open';
    public const STATUS_BOOKED      = 'booked';
    public const STATUS_BLOCKED     = 'blocked';
    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_BOOKED,
        self::STATUS_BLOCKED,
        self::STATUS_MAINTENANCE,
    ];

    protected $fillable = [
        'property_id',
        'room_id',
        'date',
        'status',
        'reservation_id',
        'blocked_reason',
        'blocked_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isBooked(): bool
    {
        return $this->status === self::STATUS_BOOKED;
    }
}
