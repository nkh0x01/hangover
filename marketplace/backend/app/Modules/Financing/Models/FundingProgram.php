<?php

declare(strict_types=1);

namespace App\Modules\Financing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingProgram extends Model
{
    protected $fillable = [
        'slug',
        'name_ka',
        'name_en',
        'provider',
        'program_type',
        'description_ka',
        'summary_ka',
        'min_amount_gel',
        'max_amount_gel',
        'co_financing_required_pct',
        'application_url',
        'contact_email',
        'contact_phone',
        'is_active',
        'is_demo',
        'opens_at',
        'closes_at',
        'tags',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'opens_at' => 'date',
            'closes_at' => 'date',
            'tags' => 'array',
            'last_checked_at' => 'datetime',
            'min_amount_gel' => 'decimal:2',
            'max_amount_gel' => 'decimal:2',
            'co_financing_required_pct' => 'integer',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(FundingProgramRule::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(FundingApplication::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('closes_at')->orWhere('closes_at', '>=', now()->toDateString());
            });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
