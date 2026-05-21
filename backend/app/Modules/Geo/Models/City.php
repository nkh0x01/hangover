<?php

declare(strict_types=1);

namespace App\Modules\Geo\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    protected static function newFactory(): CityFactory
    {
        return CityFactory::new();
    }

    protected $fillable = [
        'country_code',
        'name',
        'slug',
        'timezone',
        'default_currency',
        'default_commission_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_commission_rate' => 'decimal:4',
        ];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
