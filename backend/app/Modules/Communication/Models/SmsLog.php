<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'sms_log';

    protected $fillable = [
        'phone_e164',
        'destination',
        'masked_phone',
        'message_type',
        'purpose',
        'provider',
        'provider_msg_id',
        'provider_response',
        'cost',
        'status',
        'error_reason',
        'skip_reason',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'float',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
