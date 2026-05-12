<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteAddress extends Model
{
    protected $table = 'favorite_addresses';

    protected $fillable = [
        'user_id',
        'label',
        'address_text',
        'place_id',
        'is_home',
        'is_work',
    ];

    protected function casts(): array
    {
        return [
            'is_home' => 'boolean',
            'is_work' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
