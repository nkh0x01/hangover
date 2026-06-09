<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'is_secret'];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    // Note: encryption is owned by SettingsService::set() — see comments there.
    // The model only decrypts on read, since the encrypted-or-not decision
    // must consider `is_secret` which Eloquent mutators do NOT see in a
    // deterministic order during mass-assign / updateOrCreate.

    public function getValueAttribute(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return $raw;
        }
        if (! $this->is_secret) {
            return $raw;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function maskedValue(): string
    {
        $v = $this->value;
        if ($v === null || $v === '') {
            return '';
        }
        if (! $this->is_secret) {
            return $v;
        }
        $len = strlen($v);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        return substr($v, 0, 4).str_repeat('•', max(0, $len - 8)).substr($v, -4);
    }
}
