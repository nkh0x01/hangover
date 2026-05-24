<?php

declare(strict_types=1);

use App\Modules\Seller\Http\Controllers\SellerController;
use App\Modules\Seller\Http\Controllers\SellerProductController;
use Illuminate\Support\Facades\Route;

Route::get('sellers', [SellerController::class, 'index']);
Route::get('sellers/{seller:slug}', [SellerController::class, 'show']);
Route::get('sellers/{seller:slug}/products', [SellerController::class, 'products']);

Route::middleware('auth:sanctum')->prefix('seller')->group(function () {
    Route::post('register', [SellerController::class, 'register']);
    Route::get('me', [SellerController::class, 'me']);
    Route::patch('me', [SellerController::class, 'update']);

    // Verified-seller-only endpoints
    Route::middleware('verified.seller')->group(function () {
        Route::get('products', [SellerProductController::class, 'index']);
        Route::post('products', [SellerProductController::class, 'store']);
        Route::patch('products/{product}', [SellerProductController::class, 'update']);
        Route::delete('products/{product}', [SellerProductController::class, 'destroy']);
    });
});
