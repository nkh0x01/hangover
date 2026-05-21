<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| The mobile platform is API-only. The admin panel is served by Filament
| at /admin (its routes are auto-registered by the Filament provider).
| This file therefore only exposes a redirect to the admin panel.
|
*/

Route::redirect('/', '/admin');
