<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomMinibarItem extends Model
{
    /** @use HasFactory<\Database\Factories\RoomMinibarItemFactory> */
    use HasFactory;

    protected $fillable = [
        'room_id', 'product_id', 'par_level',
    ];

    protected function casts(): array
    {
        return [
            'par_level' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
