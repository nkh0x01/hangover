<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pricing\Models;

use App\Modules\Erp\Inventory\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    protected $table = 'price_list_items';

    protected $fillable = [
        'price_list_id', 'product_variant_id', 'price', 'vat_included',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'vat_included' => 'boolean',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
