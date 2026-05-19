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
|
| Reads are open to anyone with `logistics-dashboard` permission (HSE Agent,
| Logistics Responsible, Admin, Super Admin). Writes remain restricted to
| Admin / Super Admin only.
|
*/

// ---------- Read-only routes (HSE, Logistics Resp, Admin, Super Admin) ----------
Route::middleware(['auth', 'permission:logistics-dashboard'])->group(function () {
    Route::prefix('logistics/theft-incidents')->name('theft-incidents.')->group(function () {
        Route::get('/', [TheftIncidentController::class, 'index'])->name('index');
        Route::get('/{theftIncident}', [TheftIncidentController::class, 'show'])->name('show');
    });

    Route::get('/logistics/places', [PlaceController::class, 'index'])->name('places.index');
});

// ---------- Live GPS / surveillance routes (Admin / Super Admin only) ----------
Route::middleware(['auth', 'role:Admin|Super Admin'])->group(function () {
    Route::get('/logistics/fleet-map', [TruckController::class, 'mapPage'])
        ->name('fleet-map');

    Route::get('/transport_tracking/{transportTracking}/replay', [TransportTrackingController::class, 'replayPage'])
        ->name('transport-tracking.replay');
    Route::get('/api/trip-replay/{transportTracking}', [TripReplayController::class, 'data'])
        ->name('trip-replay.data');

    Route::post('/logistics/theft-incidents/{theftIncident}/status', [TheftIncidentController::class, 'update'])
        ->name('theft-incidents.update');

    Route::prefix('logistics/places')->name('places.')->group(function () {
        Route::get('/create', [PlaceController::class, 'create'])->name('create');
        Route::post('/', [PlaceController::class, 'store'])->name('store');
        Route::get('/{place}/edit', [PlaceController::class, 'edit'])->name('edit');
        Route::post('/{place}', [PlaceController::class, 'update'])->name('update');
        Route::post('/{place}/destroy', [PlaceController::class, 'destroy'])->name('destroy');
    });
});
