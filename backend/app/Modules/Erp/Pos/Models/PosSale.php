<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Models;

use App\Modules\Erp\Core\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $sale_uuid
 * @property int $shift_id
 * @property int $branch_id
 * @property int $cashier_id
 * @property string $subtotal
 * @property string $discount
 * @property string $vat
 * @property string $total
 * @property string $fiscal_status
 */
class PosSale extends Model
{
    use BelongsToBranch;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOIDED = 'voided';

    public const FISCAL_PENDING = 'pending';

    public const FISCAL_SENT = 'sent';

    public const FISCAL_VERIFIED = 'verified';

    public const FISCAL_FAILED = 'failed';

    protected $table = 'pos_sales';

    protected $fillable = [
        'sale_uuid', 'shift_id', 'branch_id', 'cashier_id', 'customer_id', 'channel',
        'subtotal', 'discount', 'vat', 'total', 'status', 'fiscal_status',
        'fiscal_receipt_no', 'waybill_id',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class, 'sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'sale_id');
    }
}
