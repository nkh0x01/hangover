<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\BroadcastServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

final class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes([
            'middleware' => ['api', 'auth:sanctum'],
            'prefix' => 'api/v1/broadcasting',
        ]);

        // Each module contributes its own channel auth callbacks via
        // App\Modules\<Module>\routes\channels.php (loaded from the
        // module's service provider).
        require base_path('routes/channels.php');
    }
}
