<?php

use App\Http\Controllers\PlaceController;
use App\Http\Controllers\TheftIncidentController;
use App\Http\Controllers\TransportTrackingController;
use App\Http\Controllers\TripReplayController;
use App\Http\Controllers\TruckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Theft-detection platform routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // Fleet live map
    Route::get('/logistics/fleet-map', [TruckController::class, 'mapPage'])
        ->name('fleet-map');

    // Theft incidents inbox
    Route::prefix('logistics/theft-incidents')->name('theft-incidents.')->group(function () {
        Route::get('/', [TheftIncidentController::class, 'index'])->name('index');
        Route::get('/{theftIncident}', [TheftIncidentController::class, 'show'])->name('show');
        Route::post('/{theftIncident}/status', [TheftIncidentController::class, 'update'])->name('update');
    });

    // Trip replay — attached to a transport tracking
    Route::get('/transport_tracking/{transportTracking}/replay', [TransportTrackingController::class, 'replayPage'])
        ->name('transport-tracking.replay');
    Route::get('/api/trip-replay/{transportTracking}', [TripReplayController::class, 'data'])
        ->name('trip-replay.data');

    // Places (geofences) admin
    Route::prefix('logistics/places')->name('places.')->group(function () {
        Route::get('/', [PlaceController::class, 'index'])->name('index');
        Route::get('/create', [PlaceController::class, 'create'])->name('create');
        Route::post('/', [PlaceController::class, 'store'])->name('store');
        Route::get('/{place}/edit', [PlaceController::class, 'edit'])->name('edit');
        Route::post('/{place}', [PlaceController::class, 'update'])->name('update');
        Route::post('/{place}/destroy', [PlaceController::class, 'destroy'])->name('destroy');
    });
});
