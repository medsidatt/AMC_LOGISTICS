<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Product Catalog — shared selector data + inline creation (auth-only; the
// consuming workspace already gates the action that opens the selector).
Route::middleware('auth')->prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::post('/', [ProductController::class, 'store'])->name('store');
});
