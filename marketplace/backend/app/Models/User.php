<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Commerce\Models\Order;
use App\Modules\Financing\Models\FundingApplication;
use App\Modules\Financing\Models\FundingSavedProgram;
use App\Modules\Identity\Models\Profile;
use App\Modules\Review\Models\Favorite;
use App\Modules\Review\Models\Review;
use App\Modules\Seller\Models\Seller;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'consultant']);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function fundingApplications(): HasMany
    {
        return $this->hasMany(FundingApplication::class);
    }

    public function savedFundingPrograms(): HasMany
    {
        return $this->hasMany(FundingSavedProgram::class);
    }
}
