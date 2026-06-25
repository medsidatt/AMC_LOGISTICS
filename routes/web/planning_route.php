<?php

use App\Http\Controllers\DailyDispatchController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth'], 'prefix' => 'logistics/planning', 'as' => 'logistics.planning.'], function () {
    Route::get('/', fn () => redirect('/dispatch'))->name('index');
    // Legacy URL → canonical Réalisation route. The weekly scoreboard now lives at
    // /realisation (DailyDispatchController@weekly); this keeps old links working.
    Route::get('/weekly', fn () => redirect('/realisation'))->name('weekly');
    Route::post('/', [DailyDispatchController::class, 'store'])->name('store');
    Route::post('/{dispatch}/renotify', [DailyDispatchController::class, 'renotify'])->name('renotify');
    Route::delete('/{dispatch}', [DailyDispatchController::class, 'destroy'])->name('destroy');
});
