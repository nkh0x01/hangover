<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelReservation extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelReservationFactory> */
    use HasFactory;

    public const STATUS_RECEIVED  = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_CONFLICT  = 'conflict';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_FAILED    = 'failed';

    public const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_PROCESSED,
        self::STATUS_CONFLICT,
        self::STATUS_DUPLICATE,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'channel_connection_id', 'external_id', 'hash',
        'raw_payload', 'reservation_id', 'status',
        'received_at', 'processed_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    public function isConflict(): bool
    {
        return $this->status === self::STATUS_CONFLICT;
    }
}
