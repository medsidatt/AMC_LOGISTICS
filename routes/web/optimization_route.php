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

    // Legacy fleet-roster URLs → Objectives. Authoring + outcomes were merged into
    // Objectives / the Planning Dashboard; the old roster + history screens are gone.
    Route::group(['prefix' => 'fleet-roster', 'as' => 'logistics.fleet-roster.'], function () {
        Route::get('/', fn () => redirect()->route('logistics.objectives.index'))->name('index');
        Route::get('/history', fn () => redirect()->route('logistics.objectives.index'))->name('history');
    });

    Route::group(['prefix' => 'objective-history', 'as' => 'logistics.objective-history.'], function () {
        Route::get('/', [ObjectiveHistoryController::class, 'index'])->name('index');
    });

    Route::group(['prefix' => 'availability', 'as' => 'logistics.availability.'], function () {
        Route::get('/', [\App\Http\Controllers\AvailabilityController::class, 'index'])->name('index');
        Route::post('/windows', [\App\Http\Controllers\AvailabilityController::class, 'storeWindow'])->name('windows.store');
        Route::delete('/windows/{window}', [\App\Http\Controllers\AvailabilityController::class, 'destroyWindow'])->name('windows.destroy');
    });

    Route::group(['prefix' => 'objectives', 'as' => 'logistics.objectives.'], function () {
        Route::get('/', [\App\Http\Controllers\FleetObjectiveController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\FleetObjectiveController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\FleetObjectiveController::class, 'store'])->name('store');
        Route::post('/{objective}/archive', [\App\Http\Controllers\FleetObjectiveController::class, 'archive'])->name('archive');
    });

    Route::group(['prefix' => 'affectations', 'as' => 'logistics.affectations.'], function () {
        Route::get('/', [\App\Http\Controllers\TruckDriverAssignmentController::class, 'index'])->name('index');
        Route::post('/assign', [\App\Http\Controllers\TruckDriverAssignmentController::class, 'assign'])->name('assign');
        Route::post('/release', [\App\Http\Controllers\TruckDriverAssignmentController::class, 'release'])->name('release');
    });
});
