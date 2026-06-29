<?php

use App\Http\Controllers\Auth\RoleController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'roles', 'middleware' => ['auth']], function () {
    Route::get('/', [RoleController::class, 'index'])->middleware('can:role-list')->name('roles.index');
    // Create/edit/show happen in drawers on the /roles workspace (URL-driven: ?create=1 / ?view={id} / ?edit={id}).
    Route::post('store', [RoleController::class, 'store'])->middleware('can:role-create')->name('roles.store');
    Route::put('update/{id}', [RoleController::class, 'update'])->middleware('can:role-edit')->name('roles.update');
    Route::delete('destroy/{id}', [RoleController::class, 'destroy'])->middleware('can:role-delete')->name('roles.destroy');
});
