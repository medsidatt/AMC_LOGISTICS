<?php

use App\Http\Controllers\TruckController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'trucks', 'middleware' => ['auth']], function () {
    Route::get('/info/{truck}', [TruckController::class, 'getInfo']);
});


