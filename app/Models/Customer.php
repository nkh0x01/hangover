<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'profile_json' => 'array',
        'is_vip'       => 'bool',
        'is_blocked'   => 'bool',
        'is_spam'      => 'bool',
        'last_seen_at' => 'datetime',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected function memory(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profile_json ?? [],
        );
    }

    public function patchMemory(array $patch): void
    {
        $this->profile_json = array_replace_recursive($this->profile_json ?? [], $patch);
        $this->save();
    }
}
