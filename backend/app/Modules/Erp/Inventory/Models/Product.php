<?php

declare(strict_types=1);

namespace App\Modules\Erp\Inventory\Models;

use App\Modules\Erp\Core\Models\Brand;
use Database\Factories\Erp\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'sku', 'name_ka', 'name_en', 'brand_id', 'category_id', 'vat_applicable',
        'barcode', 'unit', 'is_serialized', 'cost', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'vat_applicable' => 'boolean',
            'is_serialized' => 'boolean',
            'is_active' => 'boolean',
            'cost' => 'decimal:2',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
