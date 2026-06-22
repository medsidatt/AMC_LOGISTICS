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

Auth::routes(['reset' => false, 'register' => false]);

Route::group(['middleware' => ['auth'], 'prefix' => 'dashboard'], function () {
    Route::get('', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
});

Route::group(['middleware' => ['auth'], 'prefix' => 'settings'], function () {
    Route::get('fleet', [App\Http\Controllers\FleetSettingsController::class, 'edit'])->name('settings.fleet.edit');
    Route::put('fleet', [App\Http\Controllers\FleetSettingsController::class, 'update'])->name('settings.fleet.update');

    Route::get('operations-calendar', [App\Http\Controllers\OperationsCalendarController::class, 'edit'])->name('settings.operations-calendar.edit');
    Route::put('operations-calendar/weekdays', [App\Http\Controllers\OperationsCalendarController::class, 'updateWeekdays'])->name('settings.operations-calendar.weekdays');
    Route::post('operations-calendar/days', [App\Http\Controllers\OperationsCalendarController::class, 'storeDay'])->name('settings.operations-calendar.days.store');
    Route::delete('operations-calendar/days/{day}', [App\Http\Controllers\OperationsCalendarController::class, 'destroyDay'])->name('settings.operations-calendar.days.destroy');
});

Route::get('/admin/audit-logs', [App\Http\Controllers\AuditLogController::class, 'index'])
    ->middleware(['auth'])
    ->name('admin.audit-logs');

Route::get('/admin/audit-logs/export', [App\Http\Controllers\AuditLogController::class, 'export'])
    ->middleware(['auth'])
    ->name('admin.audit-logs.export');

Route::group(['middleware' => ['auth'], 'prefix' => 'fuel/import'], function () {
    Route::get('', [App\Http\Controllers\FuelImportController::class, 'showPage'])->name('fuel.import');
    Route::post('edk/preview', [App\Http\Controllers\FuelImportController::class, 'previewEdk'])->name('fuel.import.edk.preview');
    Route::post('edk/commit', [App\Http\Controllers\FuelImportController::class, 'commitEdk'])->name('fuel.import.edk.commit');
    Route::post('fleeti/preview', [App\Http\Controllers\FuelImportController::class, 'previewFleeti'])->name('fuel.import.fleeti.preview');
    Route::post('fleeti/commit', [App\Http\Controllers\FuelImportController::class, 'commitFleeti'])->name('fuel.import.fleeti.commit');
});

Route::group(['middleware' => ['auth'], 'prefix' => 'drivers'], function () {
    Route::get('{driver}/discipline', [App\Http\Controllers\DriverDisciplineController::class, 'index'])->name('drivers.discipline.index');
    Route::post('{driver}/discipline', [App\Http\Controllers\DriverDisciplineController::class, 'store'])->name('drivers.discipline.store');
    Route::delete('discipline/{record}', [App\Http\Controllers\DriverDisciplineController::class, 'destroy'])->name('drivers.discipline.destroy');
});

Route::get('/language/switch/{locale}', [LanguageController::class, 'switchLanguage'])->name('language.switch');
Route::get('/translations', [LanguageController::class, 'getTranslations'])->name('translations');

Route::group(['middleware' => ['auth'], 'prefix' => 'notifications', 'as' => 'notifications.'], function () {
    Route::get('/', [App\Http\Controllers\NotificationController::class, 'index'])->name('index');
    Route::post('/{id}/read', [App\Http\Controllers\NotificationController::class, 'markRead'])->name('read');
    Route::post('/read-all', [App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('read-all');
});
