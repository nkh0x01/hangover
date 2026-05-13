<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelSyncLog extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelSyncLogFactory> */
    use HasFactory;

    public const DIRECTION_IN  = 'in';
    public const DIRECTION_OUT = 'out';

    public const ACTION_PULL_RESERVATIONS   = 'pull_reservations';
    public const ACTION_PUSH_AVAILABILITY   = 'push_availability';
    public const ACTION_PUSH_RATES          = 'push_rates';
    public const ACTION_PUSH_RESTRICTIONS   = 'push_restrictions';
    public const ACTION_TEST_CONNECTION     = 'test_connection';
    public const ACTION_WEBHOOK_RECEIVED    = 'webhook_received';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED  = 'failed';

    public const TRIGGER_SCHEDULE = 'schedule';
    public const TRIGGER_EVENT    = 'event';
    public const TRIGGER_MANUAL   = 'manual';
    public const TRIGGER_WEBHOOK  = 'webhook';

    protected $fillable = [
        'channel_connection_id', 'direction', 'action', 'status',
        'payload_summary', 'response_summary', 'error',
        'duration_ms', 'started_at', 'finished_at', 'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'payload_summary' => 'array',
            'response_summary' => 'array',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }
}
