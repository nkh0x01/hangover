<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Models;

use App\Modules\Erp\Core\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property string $status
 * @property string $opening_cash
 * @property string|null $closing_cash
 */
class PosShift extends Model
{
    use BelongsToBranch;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'pos_shifts';

    protected $fillable = [
        'branch_id', 'user_id', 'status', 'opening_cash', 'closing_cash',
        'x_report', 'z_report', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'x_report' => 'array',
            'z_report' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class, 'shift_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
