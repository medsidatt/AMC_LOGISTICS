<?php

use App\Http\Controllers\DailyDispatchController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth'], 'prefix' => 'logistics/planning', 'as' => 'logistics.planning.'], function () {
    Route::get('/', [DailyDispatchController::class, 'index'])->name('index');
    Route::get('/weekly', [DailyDispatchController::class, 'weekly'])->name('weekly');
    Route::post('/', [DailyDispatchController::class, 'store'])->name('store');
    Route::post('/{dispatch}/renotify', [DailyDispatchController::class, 'renotify'])->name('renotify');
    Route::delete('/{dispatch}', [DailyDispatchController::class, 'destroy'])->name('destroy');
});
