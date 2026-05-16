<?php

use App\Http\Controllers\Webhooks\GadgetWebhookController;
use App\Http\Controllers\Webhooks\MetaWebhookController;
use App\Http\Middleware\VerifyMetaSignature;
use App\Http\Middleware\VerifyWooSignature;
use App\Http\Middleware\WebhookRateLimit;
use Illuminate\Support\Facades\Route;

/*
| Mounted at /webhooks with middleware group "webhook"
| (see bootstrap/app.php).
*/

Route::get('{channel}', [MetaWebhookController::class, 'verify'])
    ->where('channel', 'whatsapp|messenger|instagram|facebook')
    ->name('verify');

Route::post('{channel}', [MetaWebhookController::class, 'receive'])
    ->where('channel', 'whatsapp|messenger|instagram|facebook')
    ->name('receive');

// gadget.ge (WooCommerce) → us: product/stock/order/coupon push events.
Route::post('gadget', [GadgetWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyMetaSignature::class])
    ->middleware([VerifyWooSignature::class, WebhookRateLimit::class])
    ->name('gadget');
