<?php

declare(strict_types=1);

namespace App\Modules\Erp\Inventory\Models;

use App\Modules\Erp\Core\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * On-hand quantity per variant per branch. Intentionally NOT branch-scoped:
 * stock is inherently cross-branch (transfers, central reporting), so the
 * branch is an explicit dimension rather than an ambient filter.
 */
class StockLevel extends Model
{
    protected $table = 'stock_levels';

    protected $fillable = [
        'product_variant_id', 'branch_id', 'qty', 'reserved_qty', 'min_qty', 'max_qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'reserved_qty' => 'integer',
            'min_qty' => 'integer',
            'max_qty' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
