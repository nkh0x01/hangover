<?php

declare(strict_types=1);

namespace App\Modules\Driver\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDocument extends Model
{
    protected $table = 'driver_documents';

    protected $fillable = [
        'driver_id',
        'doc_type',
        'file_path',
        'file_sha256',
        'expires_on',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
