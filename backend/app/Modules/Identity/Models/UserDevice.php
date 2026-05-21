<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'device_uuid',
        'platform',
        'app_version',
        'os_version',
        'fcm_token',
        'voip_token',
        'push_enabled',
        'last_active_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
