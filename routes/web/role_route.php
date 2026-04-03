<?php

use App\Http\Controllers\Auth\RoleController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'roles', 'middleware' => ['auth']], function () {
    Route::get('/', [RoleController::class, 'index'])->middleware('can:role-list')->name('roles.index');
    Route::get('create', [RoleController::class, 'create'])->middleware('can:role-create')->name('roles.create');
    Route::get('edit/{id}', [RoleController::class, 'edit'])->middleware('can:role-edit')->name('roles.edit');
    Route::get('show/{id}', [RoleController::class, 'show'])->middleware('can:role-show')->name('roles.show');
    Route::post('store', [RoleController::class, 'store'])->middleware('can:role-create')->name('roles.store');
    Route::put('update/{id}', [RoleController::class, 'update'])->middleware('can:role-edit')->name('roles.update');
    Route::delete('destroy/{id}', [RoleController::class, 'destroy'])->middleware('can:role-delete')->name('roles.destroy');
});
