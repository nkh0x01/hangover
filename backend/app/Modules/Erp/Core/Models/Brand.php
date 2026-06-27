<?php

declare(strict_types=1);

namespace App\Modules\Erp\Core\Models;

use Database\Factories\Erp\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected $table = 'brands';

    protected $fillable = [
        'code', 'name', 'is_flagship', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_flagship' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    protected static function newFactory(): BrandFactory
    {
        return BrandFactory::new();
    }
}
