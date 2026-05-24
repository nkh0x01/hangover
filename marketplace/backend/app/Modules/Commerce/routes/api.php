<?php

declare(strict_types=1);

use App\Modules\Commerce\Http\Controllers\CartController;
use App\Modules\Commerce\Http\Controllers\CheckoutController;
use App\Modules\Commerce\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Cart works for both authenticated buyers and guests (cookie session).
Route::get('cart', [CartController::class, 'show']);
Route::post('cart/items', [CartController::class, 'add']);
Route::patch('cart/items/{item}', [CartController::class, 'update']);
Route::delete('cart/items/{item}', [CartController::class, 'destroy']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('checkout', [CheckoutController::class, 'store']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order:number}', [OrderController::class, 'show']);
    Route::post('orders/{order:number}/cancel', [OrderController::class, 'cancel']);
});
