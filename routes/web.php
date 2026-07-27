<?php

use App\Http\Controllers\Admin\AdminViewController;
use App\Http\Controllers\Payments\PaymentController;
use Illuminate\Support\Facades\Route;

// Public root → land on the login page
Route::get('/', fn () => redirect('/admin/login'));

// Bank of Georgia payment endpoints.
// The callback is a server-to-server POST from BOG (no session) → CSRF-exempt
// in bootstrap/app.php. return/fail are the customer's browser redirect targets.
Route::post('/payments/bog/callback', [PaymentController::class, 'bogCallback'])->name('payments.bog.callback');
Route::get('/payments/return', [PaymentController::class, 'success'])->name('payments.return');
Route::get('/payments/fail', [PaymentController::class, 'fail'])->name('payments.fail');

// Standalone login (no auth)
Route::get('/admin/login', [AdminViewController::class, 'login'])->name('admin.login');

// Bare /admin → dashboard (client-side auth check kicks in)
Route::get('/admin', fn () => redirect('/admin/dashboard'));

// Each admin section gets its own server-routed Blade view.
// Auth is enforced client-side via Sanctum token (redirects to /admin/login if missing).
Route::prefix('admin')->group(function () {
    Route::get('dashboard',       [AdminViewController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('inbox',           [AdminViewController::class, 'inbox'])->name('admin.inbox');
    Route::get('conversations',   [AdminViewController::class, 'conversations'])->name('admin.conversations');
    Route::get('comments',        [AdminViewController::class, 'comments'])->name('admin.comments');
    Route::get('ai-settings',     [AdminViewController::class, 'aiSettings'])->name('admin.ai-settings');
    Route::get('integrations',    [AdminViewController::class, 'integrations'])->name('admin.integrations');
    Route::get('products',        [AdminViewController::class, 'products'])->name('admin.products');
    Route::get('orders',          [AdminViewController::class, 'orders'])->name('admin.orders');
    Route::get('escalations',     [AdminViewController::class, 'escalations'])->name('admin.escalations');
    Route::get('analytics',       [AdminViewController::class, 'analytics'])->name('admin.analytics');
    Route::get('settings',        [AdminViewController::class, 'settings'])->name('admin.settings');
    Route::get('setup-checklist', [AdminViewController::class, 'setupChecklist'])->name('admin.setup-checklist');
    Route::get('health',          [AdminViewController::class, 'health'])->name('admin.health');
    Route::get('test-checklist',  [AdminViewController::class, 'testChecklist'])->name('admin.test-checklist');
    Route::get('meta-readiness',  [AdminViewController::class, 'metaReadiness'])->name('admin.meta-readiness');
    Route::get('privacy-safety',  [AdminViewController::class, 'privacySafety'])->name('admin.privacy-safety');
    Route::get('messenger-beta',  [AdminViewController::class, 'messengerBeta'])->name('admin.messenger-beta');
});

// Anything else under /admin → bounce to dashboard
Route::get('/admin/{any}', fn () => redirect('/admin/dashboard'))->where('any', '.*');
