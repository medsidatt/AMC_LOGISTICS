<?php

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MicrosoftAuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/auth/microsoft', [MicrosoftAuthController::class, 'redirect']);
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback']);

Route::get('/', function () {
    return redirect()->route('home');
});

Auth::routes(['reset' => false]);

Route::group(['middleware' => ['auth'], 'prefix' => 'dashboard'], function () {
    Route::get('', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
});

Route::group(['middleware' => ['auth'], 'prefix' => 'settings'], function () {
    Route::get('fleet', [App\Http\Controllers\FleetSettingsController::class, 'edit'])->name('settings.fleet.edit');
    Route::put('fleet', [App\Http\Controllers\FleetSettingsController::class, 'update'])->name('settings.fleet.update');
    Route::post('fleet/monthly-target', [App\Http\Controllers\FleetSettingsController::class, 'updateMonthlyTarget'])->name('settings.fleet.monthly-target');
});

Route::group(['middleware' => ['auth'], 'prefix' => 'drivers'], function () {
    Route::get('{driver}/discipline', [App\Http\Controllers\DriverDisciplineController::class, 'index'])->name('drivers.discipline.index');
    Route::post('{driver}/discipline', [App\Http\Controllers\DriverDisciplineController::class, 'store'])->name('drivers.discipline.store');
    Route::delete('discipline/{record}', [App\Http\Controllers\DriverDisciplineController::class, 'destroy'])->name('drivers.discipline.destroy');
});

Route::get('/language/switch/{locale}', [LanguageController::class, 'switchLanguage'])->name('language.switch');
Route::get('/translations', [LanguageController::class, 'getTranslations'])->name('translations');
