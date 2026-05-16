<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiPrompt extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public static function active(string $slug): ?self
    {
        return static::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
    }
}
