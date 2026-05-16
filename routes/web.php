<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'admin.layout');
Route::view('/admin', 'admin.layout');
Route::view('/admin/{any}', 'admin.layout')->where('any', '.*');
