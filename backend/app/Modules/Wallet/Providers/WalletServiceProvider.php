<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Providers;

use App\Modules\Wallet\Services\WalletPoster;
use Illuminate\Support\ServiceProvider;

final class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WalletPoster::class);
    }

    public function boot(): void {}
}
