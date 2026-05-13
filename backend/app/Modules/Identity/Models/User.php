<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Driver\Models\Driver;
use App\Support\Ulid;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Canonical User model. Lives in the Identity module so all auth +
 * profile logic stays inside one bounded context.
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'ulid',
        'type',
        'first_name',
        'last_name',
        'phone_e164',
        'phone_verified_at',
        'email',
        'email_verified_at',
        'password',
        'avatar_path',
        'locale',
        'status',
        'suspended_at',
        'suspension_reason',
        'suspended_by_user_id',
        'referral_code',
        'referred_by_user_id',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->ulid ??= Ulid::new();
            // ULID first 10 chars are pure timestamp — taking the
            // random suffix avoids collisions on rapid signups.
            $user->referral_code ??= strtoupper(substr(Ulid::new(), -8));
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->type, ['admin', 'dispatcher'], true)
            && $this->status === 'active';
    }

    public function getFilamentName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ($this->email ?? $this->phone_e164 ?? 'User');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(UserOauthIdentity::class);
    }

    public function favoriteAddresses(): HasMany
    {
        return $this->hasMany(FavoriteAddress::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(\App\Modules\Support\Models\FraudFlag::class);
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, ['suspended', 'banned'], true);
    }
}
