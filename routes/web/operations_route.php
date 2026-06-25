<?php

use App\Http\Controllers\OperationsController;
use Illuminate\Support\Facades\Route;

/*
 * Operations — flat navigation: one route per workflow (no workspace, no tabs).
 * Each workflow is the destination; the Logistics Manager reaches it in one click.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('/operations', [OperationsController::class, 'center'])->name('operations.center');
    Route::get('/planning', [OperationsController::class, 'planning'])->middleware('permission:fleet-roster-plan')->name('operations.planning');
    Route::get('/planning/objectives', [OperationsController::class, 'planningObjectives'])->middleware('permission:fleet-roster-plan')->name('operations.planning.objectives');
    Route::get('/planning/availability', [OperationsController::class, 'planningAvailability'])->middleware('permission:fleet-roster-plan')->name('operations.planning.availability');
    Route::get('/planning/calendar', [OperationsController::class, 'planningCalendar'])->middleware('permission:fleet-settings-edit')->name('operations.planning.calendar');

    // Réalisation — execution monitoring (planifié vs réalisé per truck). Reuses the
    // existing scoreboard for now; the full consolidation (Suivi Transport + map +
    // fuel + live) lands in the Réalisation build phase.
    Route::get('/realisation', [\App\Http\Controllers\DailyDispatchController::class, 'weekly'])->middleware('permission:daily-dispatch-list')->name('operations.realisation');
    Route::get('/dispatch', [OperationsController::class, 'dispatch'])->middleware('permission:daily-dispatch-list')->name('operations.dispatch');
    Route::get('/assignments', [OperationsController::class, 'crew'])->middleware('permission:driver-truck-assign')->name('operations.assignments');
    Route::get('/reconciliation', [OperationsController::class, 'reconciliation'])->middleware('permission:live-fleet-view')->name('operations.reconciliation');
    Route::get('/exceptions', [OperationsController::class, 'exceptions'])->middleware('permission:daily-dispatch-list')->name('operations.exceptions');
});
