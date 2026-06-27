<?php

declare(strict_types=1);

namespace App\Modules\Erp\Integration\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'integration_logs';

    protected $fillable = [
        'ulid', 'provider', 'operation', 'request', 'response', 'http_status',
        'success', 'verified', 'idempotency_key', 'reference', 'error',
        'ref_type', 'ref_id',
    ];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'response' => 'array',
            'success' => 'boolean',
            'verified' => 'boolean',
            'http_status' => 'integer',
        ];
    }
}
