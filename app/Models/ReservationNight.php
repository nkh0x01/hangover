<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationNight extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationNightFactory> */
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'date',
        'room_id',
        'nightly_rate',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'nightly_rate' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
