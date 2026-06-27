<?php

declare(strict_types=1);

namespace App\Modules\Erp\Procurement\Models;

use App\Modules\Erp\Inventory\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property int $product_variant_id
 * @property int $qty
 * @property string $unit_cost
 * @property array<int, string>|null $serial_nos
 */
class GoodsReceiptLine extends Model
{
    protected $table = 'goods_receipt_lines';

    protected $fillable = [
        'goods_receipt_id', 'product_variant_id', 'qty', 'unit_cost', 'serial_nos',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_cost' => 'decimal:2',
            'serial_nos' => 'array',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
