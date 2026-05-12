<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'ride_id', 'customer_id', 'provider', 'provider_intent_id', 'method',
        'amount', 'currency', 'status', 'failure_code', 'captured_at', 'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'captured_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }
}
