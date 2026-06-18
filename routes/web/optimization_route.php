<?php

use App\Http\Controllers\ClientDemandController;
use App\Http\Controllers\FleetOptimizationController;
use App\Http\Controllers\ObjectiveHistoryController;
use App\Http\Controllers\TruckRestWindowController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth'], 'prefix' => 'logistics'], function () {
    Route::group(['prefix' => 'optimization', 'as' => 'logistics.optimization.'], function () {
        Route::get('/', [FleetOptimizationController::class, 'index'])->name('index');
        Route::get('/capacity', [FleetOptimizationController::class, 'capacity'])->name('capacity');
        Route::post('/run', [FleetOptimizationController::class, 'run'])->name('run');
        Route::put('/assignments/{assignment}', [FleetOptimizationController::class, 'updateAssignment'])->name('assignments.update');
        Route::post('/assignments/{assignment}/cancel', [FleetOptimizationController::class, 'cancelAssignment'])->name('assignments.cancel');
    });

    Route::group(['prefix' => 'demands', 'as' => 'logistics.demands.'], function () {
        Route::get('/', [ClientDemandController::class, 'index'])->name('index');
        Route::get('/create', [ClientDemandController::class, 'create'])->name('create');
        Route::post('/', [ClientDemandController::class, 'store'])->name('store');
        Route::get('/{demand}/edit', [ClientDemandController::class, 'edit'])->name('edit');
        Route::put('/{demand}', [ClientDemandController::class, 'update'])->name('update');
        Route::delete('/{demand}', [ClientDemandController::class, 'destroy'])->name('destroy');
    });

    Route::group(['prefix' => 'rest-windows', 'as' => 'logistics.rest-windows.'], function () {
        Route::get('/', [TruckRestWindowController::class, 'index'])->name('index');
        Route::get('/create', [TruckRestWindowController::class, 'create'])->name('create');
        Route::post('/', [TruckRestWindowController::class, 'store'])->name('store');
        Route::delete('/{restWindow}', [TruckRestWindowController::class, 'destroy'])->name('destroy');
    });

    Route::group(['prefix' => 'fleet-roster', 'as' => 'logistics.fleet-roster.'], function () {
        Route::get('/', [\App\Http\Controllers\FleetRosterController::class, 'index'])->name('index');
        Route::get('/history', [\App\Http\Controllers\FleetRosterController::class, 'history'])->name('history');
        Route::post('/apply', [\App\Http\Controllers\FleetRosterController::class, 'apply'])->name('apply');
        Route::post('/', [\App\Http\Controllers\FleetRosterController::class, 'store'])->name('store');
    });

    Route::group(['prefix' => 'objective-history', 'as' => 'logistics.objective-history.'], function () {
        Route::get('/', [ObjectiveHistoryController::class, 'index'])->name('index');
    });
});
