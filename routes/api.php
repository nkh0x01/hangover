<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('admin/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin.auth'])->prefix('admin')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('inbox', [InboxController::class, 'index']);
    Route::get('inbox/{id}', [InboxController::class, 'show']);
    Route::post('inbox/{id}/reply', [InboxController::class, 'reply']);
    Route::post('inbox/{id}/takeover', [InboxController::class, 'takeover']);
    Route::post('inbox/{id}/release', [InboxController::class, 'release']);
    Route::post('inbox/{id}/memory', [InboxController::class, 'updateMemory']);
    Route::post('inbox/{id}/status', [InboxController::class, 'setStatus']);
    Route::post('inbox/{id}/spam', [InboxController::class, 'flagSpam']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::post('orders/{id}/confirm', [OrderController::class, 'confirm']);
    Route::post('orders/{id}/payment-link', [OrderController::class, 'paymentLink']);
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('settings/prompts', [SettingsController::class, 'listPrompts']);
    Route::get('settings/prompts/{slug}', [SettingsController::class, 'showPrompt']);
    Route::post('settings/prompts', [SettingsController::class, 'savePrompt']);
    Route::post('settings/prompts/{id}/activate', [SettingsController::class, 'activatePrompt']);
});
