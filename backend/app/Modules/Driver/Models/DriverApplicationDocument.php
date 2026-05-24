<?php

declare(strict_types=1);

namespace App\Modules\Driver\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverApplicationDocument extends Model
{
    protected $table = 'driver_application_documents';

    protected $fillable = [
        'application_id',
        'doc_type',
        'file_path',
        'file_sha256',
        'mime_type',
        'size_bytes',
        'status',
        'review_notes',
        'reviewed_at',
        'reviewed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DriverApplication::class, 'application_id');
    }
}
