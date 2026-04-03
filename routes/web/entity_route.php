<?php

use App\Http\Controllers\EntityController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'entities', 'middleware' => ['auth']], function (){
    Route::get('/', [EntityController::class, 'index'])->name('entities.index');
    Route::get('/create', [EntityController::class, 'create'])->name('entities.create');
    Route::post('/store', [EntityController::class, 'store'])->name('entities.store');
    Route::get('/show/{entity}', [EntityController::class, 'show'])->name('entities.show');
    Route::get('/edit/{entity}', [EntityController::class, 'edit'])->name('entities.edit');
    Route::put('/update/{entity}', [EntityController::class, 'update'])->name('entities.update');
    Route::delete('/destroy/{entity}', [EntityController::class, 'destroy'])->name('entities.destroy');
});
