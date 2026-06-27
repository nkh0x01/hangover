<?php

declare(strict_types=1);

namespace App\Modules\Erp\Core\Models;

use Database\Factories\Erp\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected $table = 'branches';

    protected $fillable = [
        'code', 'name', 'brand_id', 'city', 'address', 'rs_branch_code', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }
}
