<?php

declare(strict_types=1);

namespace App\Modules\Erp\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a branch-owned model to the caller's active
 * branch. HQ/reporting roles bypass it by leaving BranchContext unset,
 * which keeps multi-location data isolated by default while still allowing
 * central reads across all 16 branches.
 */
final class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = BranchContext::current();

        if ($branchId !== null) {
            $builder->where($model->getTable().'.branch_id', $branchId);
        }
    }
}
