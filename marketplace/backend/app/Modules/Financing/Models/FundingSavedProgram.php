<?php

declare(strict_types=1);

namespace App\Modules\Financing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingSavedProgram extends Model
{
    protected $fillable = [
        'user_id',
        'funding_program_id',
        'match_percentage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'match_percentage' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FundingProgram::class, 'funding_program_id');
    }
}
