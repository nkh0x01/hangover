<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'product_skus_json'       => 'array',
        'product_categories_json' => 'array',
        'excluded_skus_json'      => 'array',
        'individual_use'          => 'bool',
        'free_shipping'           => 'bool',
        'is_active'               => 'bool',
        'expires_at'              => 'datetime',
        'synced_at'               => 'datetime',
        'amount'                  => 'decimal:2',
        'min_amount'              => 'decimal:2',
        'max_amount'              => 'decimal:2',
    ];

    public function isValid(): bool
    {
        if (! $this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        return true;
    }

    public function appliesToSku(string $sku, ?string $categorySlug = null): bool
    {
        if (! $this->isValid()) return false;

        $skus = (array) ($this->product_skus_json ?? []);
        $cats = (array) ($this->product_categories_json ?? []);

        if (empty($skus) && empty($cats)) return true;

        if (in_array($sku, $skus, true)) return true;
        if ($categorySlug && collect($cats)->pluck('slug')->contains($categorySlug)) return true;

        return false;
    }
}
