<?php

declare(strict_types=1);

namespace App\Modules\Support\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ?Carbon $acknowledged_at
 * @property ?Carbon $resolved_at
 * @property string $status
 * @property int $user_id
 * @property ?int $ride_id
 */
class SosEvent extends Model
{
    protected $table = 'sos_events';

    protected $fillable = [
        'ride_id',
        'user_id',
        'body',
        'status',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }
}
