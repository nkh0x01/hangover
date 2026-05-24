<?php

declare(strict_types=1);

namespace App\Modules\Financing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingProgramRule extends Model
{
    protected $fillable = [
        'funding_program_id',
        'rule_type',
        'criteria',
        'weight',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'weight' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FundingProgram::class, 'funding_program_id');
    }
}
