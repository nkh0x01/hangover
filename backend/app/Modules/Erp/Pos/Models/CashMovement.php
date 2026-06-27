<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Models;

use App\Modules\Erp\Core\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shift_id
 * @property int $branch_id
 * @property string $type
 * @property string $amount
 */
class CashMovement extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_PAYOUT = 'payout';

    public const TYPE_DEPOSIT = 'deposit';

    protected $table = 'cash_movements';

    protected $fillable = [
        'shift_id', 'branch_id', 'type', 'amount', 'reason', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }
}
