<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Product extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id', 'category_id',
        'name', 'sku', 'barcode',
        'cost_price', 'sale_price', 'tax_rate',
        'track_stock', 'low_stock_threshold', 'active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'track_stock' => 'boolean',
            'low_stock_threshold' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function minibarRooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_minibar_items')
            ->withPivot('par_level')
            ->withTimestamps();
    }

    public function totalStock(): int
    {
        return (int) $this->stocks()->sum('quantity');
    }
}
