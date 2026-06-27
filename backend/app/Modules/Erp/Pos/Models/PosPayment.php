<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property string $method
 * @property string $amount
 * @property string $status
 */
class PosPayment extends Model
{
    public const METHOD_CASH = 'cash';

    public const METHOD_CARD = 'card';

    public const METHOD_TBC_CASHBACK = 'tbc_cashback';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    protected $table = 'pos_payments';

    protected $fillable = [
        'sale_id', 'method', 'amount', 'terminal_txn_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'sale_id');
    }
}
