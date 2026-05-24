<?php

declare(strict_types=1);

namespace App\Modules\Financing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingApplicationDocument extends Model
{
    protected $fillable = [
        'funding_application_id',
        'type',
        'file_path',
        'original_name',
        'mime',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FundingApplication::class, 'funding_application_id');
    }
}
