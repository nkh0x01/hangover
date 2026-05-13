<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    /** @use HasFactory<\Database\Factories\RoomTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'slug',
        'base_price',
        'capacity_adults',
        'capacity_children',
        'max_occupancy',
        'bed_type',
        'size_sqm',
        'description',
        'default_check_in_time',
        'default_check_out_time',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'capacity_adults' => 'integer',
            'capacity_children' => 'integer',
            'max_occupancy' => 'integer',
            'size_sqm' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
