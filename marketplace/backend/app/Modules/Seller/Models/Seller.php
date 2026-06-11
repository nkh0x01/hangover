<?php

declare(strict_types=1);

namespace App\Modules\Seller\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'business_name',
        'legal_form',
        'tax_id',
        'sector',
        'region',
        'municipality',
        'business_age_months',
        'annual_revenue_gel',
        'employees_count',
        'is_woman_owned',
        'is_youth_owned',
        'is_mountainous_region',
        'is_startup',
        'is_agriculture',
        'is_made_in_georgia_verified',
        'story',
        'cover_path',
        'logo_path',
        'website_url',
        'facebook_url',
        'instagram_url',
        'verification_status',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_woman_owned' => 'boolean',
            'is_youth_owned' => 'boolean',
            'is_mountainous_region' => 'boolean',
            'is_startup' => 'boolean',
            'is_agriculture' => 'boolean',
            'is_made_in_georgia_verified' => 'boolean',
            'verified_at' => 'datetime',
            'business_age_months' => 'integer',
            'employees_count' => 'integer',
            'annual_revenue_gel' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('verification_status', 'approved');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
