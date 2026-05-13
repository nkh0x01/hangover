<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRoomPrice extends Model
{
    /** @use HasFactory<\Database\Factories\DailyRoomPriceFactory> */
    use HasFactory;

    public const SOURCE_MANUAL  = 'manual';
    public const SOURCE_RULE    = 'rule';
    public const SOURCE_CHANNEL = 'channel';

    protected $fillable = [
        'property_id', 'room_type_id', 'room_id',
        'date', 'price',
        'min_stay', 'max_stay',
        'closed_to_arrival', 'closed_to_departure',
        'available_inventory',
        'source', 'rule_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_stay' => 'integer',
            'max_stay' => 'integer',
            'closed_to_arrival' => 'boolean',
            'closed_to_departure' => 'boolean',
            'available_inventory' => 'integer',
        ];
    }

    /**
     * Store the date as 'Y-m-d' (no time portion) so whereIn() against
     * the bare date strings produced by Period::nightDates() matches.
     * Reads return a CarbonImmutable for ergonomic access.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? CarbonImmutable::parse($value)->startOfDay() : null,
            set: fn ($value) => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : ($value === null ? null : CarbonImmutable::parse((string) $value)->toDateString()),
        );
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class, 'rule_id');
    }
}
