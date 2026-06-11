<?php

declare(strict_types=1);

namespace App\Modules\Financing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingConsultantNote extends Model
{
    protected $fillable = [
        'funding_application_id',
        'consultant_id',
        'note_ka',
        'next_action',
        'next_action_due_at',
    ];

    protected function casts(): array
    {
        return [
            'next_action_due_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FundingApplication::class, 'funding_application_id');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }
}
