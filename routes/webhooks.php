<?php

use App\Http\Controllers\Webhooks\MetaWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Mounted at /webhooks with middleware group "webhook"
| (see bootstrap/app.php).
*/

Route::get('{channel}',  [MetaWebhookController::class, 'verify'])
    ->where('channel', 'whatsapp|messenger|instagram|facebook')
    ->name('verify');

Route::post('{channel}', [MetaWebhookController::class, 'receive'])
    ->where('channel', 'whatsapp|messenger|instagram|facebook')
    ->name('receive');
