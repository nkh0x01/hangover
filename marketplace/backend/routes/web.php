<?php

declare(strict_types=1);

use App\Http\Controllers\AuthWebController;
use App\Http\Controllers\CartWebController;
use App\Http\Controllers\CatalogWebController;
use App\Http\Controllers\CmsWebController;
use App\Http\Controllers\FinancingWebController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SellersWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Catalog
Route::get('catalogue', [CatalogWebController::class, 'index'])->name('catalog.index');
Route::get('category/{category:slug}', [CatalogWebController::class, 'category'])->name('catalog.category');
Route::get('product/{product:slug}', [CatalogWebController::class, 'product'])->name('product.show');

// Sellers
Route::get('sellers', [SellersWebController::class, 'index'])->name('sellers.index');
Route::get('seller/{seller:slug}', [SellersWebController::class, 'show'])->name('seller.show');

// Cart
Route::get('cart', [CartWebController::class, 'show'])->name('cart.show');
Route::post('cart/items', [CartWebController::class, 'add'])->name('cart.add');
Route::delete('cart/items/{item}', [CartWebController::class, 'remove'])->name('cart.remove');

// Financing — public
Route::get('financing', [FinancingWebController::class, 'landing'])->name('financing.landing');
Route::get('financing/questionnaire', [FinancingWebController::class, 'questionnaire'])->name('financing.questionnaire');
Route::post('financing/recommendations', [FinancingWebController::class, 'recommendations'])->name('financing.recommendations.post');
Route::get('financing/programs', [FinancingWebController::class, 'programs'])->name('financing.programs.index');
Route::get('financing/programs/{program:slug}', [FinancingWebController::class, 'program'])->name('financing.programs.show');

// CMS
Route::get('p/{page:slug}', [CmsWebController::class, 'page'])->name('cms.page');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthWebController::class, 'login']);
    Route::get('register', [AuthWebController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthWebController::class, 'register']);
});

Route::post('logout', [AuthWebController::class, 'logout'])->name('logout')->middleware('auth');

// Authenticated buyer area
Route::middleware('auth')->group(function () {
    Route::get('checkout', [CartWebController::class, 'checkout'])->name('checkout.show');
    Route::post('checkout', [CartWebController::class, 'placeOrder'])->name('checkout.place');
    Route::get('account/orders', [CartWebController::class, 'orders'])->name('account.orders');

    Route::post('financing/applications', [FinancingWebController::class, 'startApplication'])->name('financing.applications.start');

    // Seller onboarding
    Route::get('seller/register', [SellersWebController::class, 'onboarding'])->name('seller.onboarding');
    Route::post('seller/register', [SellersWebController::class, 'submitOnboarding'])->name('seller.onboarding.submit');
});
