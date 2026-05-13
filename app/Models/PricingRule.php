<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PricingRule extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\PricingRuleFactory> */
    use Auditable, HasFactory;

    public const TYPE_WEEKEND        = 'weekend';
    public const TYPE_SEASONAL       = 'seasonal';
    public const TYPE_HOLIDAY        = 'holiday';
    public const TYPE_OCCUPANCY      = 'occupancy';
    public const TYPE_LAST_MINUTE    = 'last_minute';
    public const TYPE_LENGTH_OF_STAY = 'length_of_stay';

    public const TYPES = [
        self::TYPE_WEEKEND,
        self::TYPE_SEASONAL,
        self::TYPE_HOLIDAY,
        self::TYPE_OCCUPANCY,
        self::TYPE_LAST_MINUTE,
        self::TYPE_LENGTH_OF_STAY,
    ];

    public const SCOPE_PROPERTY  = 'property';
    public const SCOPE_ROOM_TYPE = 'room_type';
    public const SCOPE_ROOM      = 'room';

    public const SCOPES = [
        self::SCOPE_PROPERTY,
        self::SCOPE_ROOM_TYPE,
        self::SCOPE_ROOM,
    ];

    protected $fillable = [
        'property_id', 'name', 'type', 'priority',
        'scope', 'room_type_id', 'room_id',
        'conditions', 'action',
        'valid_from', 'valid_to', 'active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'conditions' => 'array',
            'action' => 'array',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'active' => 'boolean',
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
