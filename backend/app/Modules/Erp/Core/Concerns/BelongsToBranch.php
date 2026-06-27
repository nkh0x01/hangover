<?php

declare(strict_types=1);

namespace App\Modules\Erp\Core\Concerns;

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Core\Scopes\BranchContext;
use App\Modules\Erp\Core\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies the branch global scope and auto-stamps branch_id on create from
 * the active BranchContext, so multi-location isolation is the default for
 * branch-owned transactional documents (purchase orders, goods receipts,
 * shifts, sales) and never depends on the caller remembering to set it.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model): void {
            if ($model->branch_id === null && BranchContext::current() !== null) {
                $model->branch_id = BranchContext::current();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
