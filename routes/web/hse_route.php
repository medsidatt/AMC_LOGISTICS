<?php

use App\Http\Controllers\HseController;
use App\Http\Controllers\LogisticsManagerController;
use Illuminate\Support\Facades\Route;

// HSE inspections
Route::group(['prefix' => 'hse/inspections', 'as' => 'hse.inspections.', 'middleware' => ['auth']], function () {
    Route::get('/', [HseController::class, 'index'])->name('index');
    Route::get('/create', [HseController::class, 'create'])->name('create');
    Route::post('/', [HseController::class, 'store'])->name('store');
    Route::get('/{inspection}', [HseController::class, 'show'])->name('show');
    Route::get('/{inspection}/edit', [HseController::class, 'edit'])->name('edit');
    Route::put('/{inspection}', [HseController::class, 'update'])->name('update');
});

// Logistics Responsible validation queue
Route::group(['prefix' => 'logistics/validation', 'as' => 'logistics.validation.', 'middleware' => ['auth']], function () {
    Route::get('/checklists', [LogisticsManagerController::class, 'pendingChecklists'])->name('checklists');
    Route::post('/checklists/{dailyChecklist}/validate', [LogisticsManagerController::class, 'validateChecklist'])->name('checklists.validate');

    Route::get('/inspections', [LogisticsManagerController::class, 'pendingInspections'])->name('inspections');
    Route::post('/inspections/{inspection}/validate', [LogisticsManagerController::class, 'validateInspection'])->name('inspections.validate');

    Route::post('/inspection-issues/{issue}/resolve', [LogisticsManagerController::class, 'resolveInspectionIssue'])->name('inspection-issues.resolve');
});
