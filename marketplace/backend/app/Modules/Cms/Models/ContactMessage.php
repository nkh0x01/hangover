<?php

declare(strict_types=1);

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'body_ka',
        'is_handled',
    ];

    protected function casts(): array
    {
        return [
            'is_handled' => 'boolean',
        ];
    }
}
