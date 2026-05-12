<?php

declare(strict_types=1);

namespace App\Modules\Rating\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $table = 'ratings';

    protected $fillable = [
        'ride_id', 'rater_user_id', 'ratee_user_id', 'score', 'tags', 'comment',
    ];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }
}
