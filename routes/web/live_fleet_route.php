<?php

use App\Http\Controllers\LiveFleetController;
use App\Http\Controllers\TicketGapController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth'], 'prefix' => 'logistics/live', 'as' => 'logistics.live.'], function () {
    Route::get('/', [LiveFleetController::class, 'index'])->name('index');
    Route::get('/state', [LiveFleetController::class, 'state'])->name('state');
    Route::get('/{dispatch}', [LiveFleetController::class, 'show'])->name('show');
});

Route::group(['middleware' => ['auth'], 'prefix' => 'reports/ticket-gap', 'as' => 'reports.ticket_gap.'], function () {
    Route::get('/', fn () => redirect('/reconciliation'))->name('index');
    Route::post('/{expected}/dismiss', [TicketGapController::class, 'dismiss'])->name('dismiss');
});
