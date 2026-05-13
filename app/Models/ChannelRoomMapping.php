<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelRoomMapping extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelRoomMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'channel_connection_id', 'room_type_id', 'room_id',
        'external_room_id', 'external_room_name',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
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
