<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Models;

use App\Modules\Erp\Inventory\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $product_variant_id
 * @property int $qty
 * @property string $unit_price
 * @property string $discount
 * @property string $vat
 * @property string $cost
 */
class PosSaleItem extends Model
{
    protected $table = 'pos_sale_items';

    protected $fillable = [
        'sale_id', 'product_variant_id', 'serial_item_id', 'qty',
        'unit_price', 'discount', 'vat', 'cost',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'sale_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
