<?php

declare(strict_types=1);

namespace App\Modules\Erp\Inventory\Models;

use App\Modules\Erp\Core\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialItem extends Model
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_SOLD = 'sold';

    public const STATUS_RMA = 'rma';

    protected $table = 'serial_items';

    protected $fillable = [
        'product_variant_id', 'branch_id', 'serial_no', 'imei', 'status', 'sale_item_id',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
