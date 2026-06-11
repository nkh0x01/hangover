<?php

declare(strict_types=1);

namespace App\Modules\Financing\Models;

use App\Models\User;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingApplication extends Model
{
    protected $fillable = [
        'user_id',
        'seller_id',
        'funding_program_id',
        'status',
        'business_profile_snapshot',
        'amount_requested_gel',
        'purpose_ka',
        'assigned_consultant_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'business_profile_snapshot' => 'array',
            'amount_requested_gel' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FundingProgram::class, 'funding_program_id');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_consultant_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FundingApplicationDocument::class);
    }

    public function consultantNotes(): HasMany
    {
        return $this->hasMany(FundingConsultantNote::class);
    }
}
