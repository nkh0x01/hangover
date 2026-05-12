<?php

declare(strict_types=1);

namespace App\Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use SoftDeletes;

    protected $table = 'promo_codes';

    protected $fillable = [
        'code', 'kind', 'value', 'currency', 'max_uses', 'max_uses_per_user',
        'min_ride_amount', 'applicable_city_ids', 'valid_from', 'valid_until',
        'status', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'applicable_city_ids' => 'array',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }
}
