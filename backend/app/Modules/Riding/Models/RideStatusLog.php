<?php

declare(strict_types=1);

namespace App\Modules\Riding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideStatusLog extends Model
{
    protected $table = 'ride_status_logs';

    public $timestamps = false;

    protected $fillable = [
        'ride_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'reason',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
