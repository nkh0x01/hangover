<?php

declare(strict_types=1);

namespace App\Modules\Geo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zone extends Model
{
    protected $table = 'zones';

    protected $fillable = ['city_id', 'name', 'kind', 'priority'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
