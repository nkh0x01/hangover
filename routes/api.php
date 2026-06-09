<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\Admin\IntegrationsController;
use App\Http\Controllers\Admin\MessengerBetaController;
use App\Http\Controllers\Admin\MetaReadinessController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SetupChecklistController;
use Illuminate\Support\Facades\Route;

Route::post('admin/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin.auth'])->prefix('admin')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('inbox', [InboxController::class, 'index']);
    Route::get('inbox/employees', [InboxController::class, 'employees']);
    Route::get('inbox/{id}', [InboxController::class, 'show']);
    Route::post('inbox/{id}/reply', [InboxController::class, 'reply']);
    Route::post('inbox/{id}/ai-suggest', [InboxController::class, 'aiSuggest']);
    Route::post('inbox/{id}/takeover', [InboxController::class, 'takeover']);
    Route::post('inbox/{id}/release', [InboxController::class, 'release']);
    Route::post('inbox/{id}/assign', [InboxController::class, 'assign']);
    Route::post('inbox/{id}/read', [InboxController::class, 'markRead']);
    Route::post('inbox/{id}/unread', [InboxController::class, 'markUnread']);
    Route::post('inbox/{id}/memory', [InboxController::class, 'updateMemory']);
    Route::post('inbox/{id}/status', [InboxController::class, 'setStatus']);
    Route::post('inbox/{id}/spam', [InboxController::class, 'flagSpam']);
    Route::post('inbox/{id}/fetch-profile', [InboxController::class, 'fetchProfile']);
    Route::post('inbox/{id}/customer', [InboxController::class, 'updateCustomer']);
    Route::get('inbox/{id}/notes', [InboxController::class, 'listNotes']);
    Route::post('inbox/{id}/notes', [InboxController::class, 'addNote']);
    Route::delete('inbox/{id}/notes/{noteId}', [InboxController::class, 'removeNote']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::post('orders/{id}/confirm', [OrderController::class, 'confirm']);
    Route::post('orders/{id}/payment-link', [OrderController::class, 'paymentLink']);
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);

    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('analytics', [AnalyticsController::class, 'index']);

    // Health + Emergency Stop
    Route::get('health', [HealthController::class, 'check']);
    Route::post('health/master-toggle', [HealthController::class, 'toggleMaster']);
    Route::post('health/safe-mode', [HealthController::class, 'toggleSafeMode']);

    // Meta App Review readiness (read-only probes)
    Route::get('meta-readiness', [MetaReadinessController::class, 'index']);

    // Messenger beta metrics + ready gate
    Route::get('messenger-beta', [MessengerBetaController::class, 'index']);

    Route::get('settings/prompts', [SettingsController::class, 'listPrompts']);
    Route::get('settings/prompts/{slug}', [SettingsController::class, 'showPrompt']);
    Route::post('settings/prompts', [SettingsController::class, 'savePrompt']);
    Route::post('settings/prompts/{id}/activate', [SettingsController::class, 'activatePrompt']);

    // Integration settings
    Route::get('integrations', [IntegrationsController::class, 'all']);
    Route::get('integrations/{group}', [IntegrationsController::class, 'show']);
    Route::post('integrations', [IntegrationsController::class, 'save']);
    Route::post('integrations/{type}/test', [IntegrationsController::class, 'test']);
    Route::delete('integrations/{group}/{key}', [IntegrationsController::class, 'delete']);

    // Products
    Route::get('products/search', [ProductController::class, 'search']);
    Route::post('inbox/{id}/send-product', [ProductController::class, 'sendProduct']);
    Route::post('inbox/{id}/recommend', [ProductController::class, 'recommend']);

    // Setup checklist
    Route::get('setup-checklist', [SetupChecklistController::class, 'index']);
});
