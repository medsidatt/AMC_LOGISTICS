<?php

use App\Http\Controllers\PlaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Geofence (Place) routes
|--------------------------------------------------------------------------
|
| Places are operational reference geofences (base / provider / client /
| border) consumed silently by the GPS reconciliation pipeline to classify
| truck stops. They are NOT a user-facing GPS surface. Reads are open to
| `logistics-dashboard`; writes remain Admin / Super Admin only.
|
*/

Route::middleware(['auth', 'permission:logistics-dashboard'])->group(function () {
    Route::get('/logistics/places', [PlaceController::class, 'index'])->name('places.index');
});

Route::middleware(['auth', 'role:Admin|Super Admin'])->group(function () {
    Route::prefix('logistics/places')->name('places.')->group(function () {
        Route::get('/create', [PlaceController::class, 'create'])->name('create');
        Route::post('/', [PlaceController::class, 'store'])->name('store');
        Route::get('/{place}/edit', [PlaceController::class, 'edit'])->name('edit');
        Route::post('/{place}', [PlaceController::class, 'update'])->name('update');
        Route::post('/{place}/destroy', [PlaceController::class, 'destroy'])->name('destroy');
    });
});
