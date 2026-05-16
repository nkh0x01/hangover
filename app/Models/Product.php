<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'stock_by_branch_json' => 'array',
        'attributes_json' => 'array',
        'compatibility_json' => 'array',
        'images_json' => 'array',
        'is_active' => 'bool',
        'is_promo' => 'bool',
        'price' => 'decimal:2',
        'price_promo' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function effectivePrice(): float
    {
        return (float) ($this->price_promo ?? $this->price);
    }

    public function isInStock(?string $branch = null): bool
    {
        if ($branch && is_array($this->stock_by_branch_json)) {
            return (int) ($this->stock_by_branch_json[$branch] ?? 0) > 0;
        }

        return (int) $this->stock_total > 0;
    }

    public function primaryImage(): ?string
    {
        $imgs = $this->images_json ?? [];

        return $imgs[0] ?? null;
    }
}
