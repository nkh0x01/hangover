<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $phone_e164
 * @property string $code_hash
 * @property string $purpose
 * @property int $attempts
 * @property Carbon $sent_at
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property string|null $ip
 * @property string|null $user_agent
 */
class PhoneVerification extends Model
{
    protected $table = 'phone_verifications';

    protected $fillable = [
        'phone_e164',
        'code_hash',
        'purpose',
        'attempts',
        'sent_at',
        'expires_at',
        'consumed_at',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
