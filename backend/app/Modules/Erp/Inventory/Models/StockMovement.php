<?php

declare(strict_types=1);

namespace App\Modules\Erp\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_ADJUST = 'adjust';

    public const TYPE_INVENTORY = 'inventory';

    protected $table = 'stock_movements';

    protected $fillable = [
        'ulid', 'type', 'product_variant_id', 'from_branch_id', 'to_branch_id',
        'qty', 'cost', 'waybill_id', 'ref_type', 'ref_id', 'user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
