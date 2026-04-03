<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'projects', 'middleware' => ['auth']], function () {
    Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/create', [ProjectController::class, 'create'])->name('projects.create');
    // store
    Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/{id}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/{id}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/{id}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/{id}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // projects.assign.user
    Route::get('/{id}/assign/user', [ProjectController::class, 'assignUser'])->name('projects.assign.user');
    // projects.assign.user.store
    Route::post('/{id}/assign/user', [ProjectController::class, 'storeAssignUser'])->name('projects.assign.user.store');
    // projects.assign.user.destroy
    Route::delete('/{id}/assign/user/{userId}', [ProjectController::class, 'destroyAssignUser'])->name('projects.assign.user.destroy');

});
