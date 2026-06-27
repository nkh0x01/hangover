<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pricing\Models;

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Core\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    public const TYPE_RETAIL = 'retail';

    public const TYPE_WHOLESALE = 'wholesale';

    protected $table = 'price_lists';

    protected $fillable = [
        'name', 'brand_id', 'branch_id', 'type', 'currency', 'is_active',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }
}
