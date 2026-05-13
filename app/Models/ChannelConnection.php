<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ChannelConnection extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\ChannelConnectionFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const CHANNEL_BOOKING       = 'booking';
    public const CHANNEL_EXPEDIA       = 'expedia';
    public const CHANNEL_AIRBNB        = 'airbnb';
    public const CHANNEL_ICAL_GENERIC  = 'ical_generic';
    public const CHANNEL_MOCK          = 'mock';

    public const CHANNELS = [
        self::CHANNEL_MOCK,
        self::CHANNEL_BOOKING,
        self::CHANNEL_EXPEDIA,
        self::CHANNEL_AIRBNB,
        self::CHANNEL_ICAL_GENERIC,
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ERROR  = 'error';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_ERROR,
    ];

    protected $fillable = [
        'property_id', 'channel', 'name', 'status',
        'credentials', 'settings',
        'last_pull_at', 'last_push_at',
        'last_error', 'error_count',
    ];

    protected function casts(): array
    {
        return [
            // Credentials are encrypted on write, decrypted on read.
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'last_pull_at' => 'datetime',
            'last_push_at' => 'datetime',
            'error_count' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomMappings(): HasMany
    {
        return $this->hasMany(ChannelRoomMapping::class);
    }

    public function rateMappings(): HasMany
    {
        return $this->hasMany(ChannelRateMapping::class);
    }

    public function channelReservations(): HasMany
    {
        return $this->hasMany(ChannelReservation::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ChannelSyncLog::class);
    }

    public function isMock(): bool
    {
        return $this->channel === self::CHANNEL_MOCK;
    }
}
