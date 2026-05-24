<?php

declare(strict_types=1);

use App\Modules\Cms\Http\Controllers\CmsController;
use Illuminate\Support\Facades\Route;

Route::get('pages/{page:slug}', [CmsController::class, 'page']);
Route::get('hero/{key}', [CmsController::class, 'hero']);
Route::post('contact', [CmsController::class, 'contact'])->middleware('throttle:5,1');

Route::get('health', fn () => response()->json([
    'status' => 'ok',
    'app' => config('marketplace.name.ka'),
    'time' => now()->toIso8601String(),
]));
