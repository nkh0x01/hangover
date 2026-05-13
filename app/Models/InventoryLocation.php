<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryLocationFactory> */
    use HasFactory;

    public const TYPE_RECEPTION    = 'reception';
    public const TYPE_STORAGE      = 'storage';
    public const TYPE_ROOM_MINIBAR = 'room_minibar';

    public const TYPES = [
        self::TYPE_RECEPTION,
        self::TYPE_STORAGE,
        self::TYPE_ROOM_MINIBAR,
    ];

    protected $fillable = [
        'property_id', 'type', 'room_id', 'name', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
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

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function isMinibar(): bool
    {
        return $this->type === self::TYPE_ROOM_MINIBAR;
    }
}
