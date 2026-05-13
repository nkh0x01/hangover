<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelRateMapping extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelRateMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'channel_connection_id', 'room_type_id', 'rate_plan_id',
        'external_rate_id', 'external_rate_name',
        'markup_percent', 'markup_abs',
    ];

    protected function casts(): array
    {
        return [
            'markup_percent' => 'decimal:2',
            'markup_abs' => 'decimal:2',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
