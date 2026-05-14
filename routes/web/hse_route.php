<?php

use App\Http\Controllers\HseController;
use App\Http\Controllers\LogisticsInspectionController;
use App\Http\Controllers\LogisticsManagerController;
use Illuminate\Support\Facades\Route;

// HSE inspections — read-only viewing for ISO consumers
Route::group(['prefix' => 'hse/inspections', 'as' => 'hse.inspections.', 'middleware' => ['auth']], function () {
    Route::get('/', [HseController::class, 'index'])->name('index');
    Route::get('/{inspection}', [HseController::class, 'show'])->name('show');
});

// Logistics Responsible inspection authoring
Route::group(['prefix' => 'logistics/inspections', 'as' => 'logistics.inspections.', 'middleware' => ['auth']], function () {
    Route::get('/create', [LogisticsInspectionController::class, 'create'])->name('create');
    Route::post('/', [LogisticsInspectionController::class, 'store'])->name('store');
    Route::get('/{inspection}/edit', [LogisticsInspectionController::class, 'edit'])->name('edit');
    Route::put('/{inspection}', [LogisticsInspectionController::class, 'update'])->name('update');
});

// Logistics Responsible validation queue (weekly checklists + leftover issue resolution)
Route::group(['prefix' => 'logistics/validation', 'as' => 'logistics.validation.', 'middleware' => ['auth']], function () {
    Route::get('/checklists', [LogisticsManagerController::class, 'pendingChecklists'])->name('checklists');
    Route::post('/checklists/{dailyChecklist}/validate', [LogisticsManagerController::class, 'validateChecklist'])->name('checklists.validate');

    Route::post('/inspection-issues/{issue}/resolve', [LogisticsManagerController::class, 'resolveInspectionIssue'])->name('inspection-issues.resolve');
});
