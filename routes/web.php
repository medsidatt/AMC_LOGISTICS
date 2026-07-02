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

    // R2.1 — Executive Command Center (first end-to-end pipeline consumer). Additive and
    // parallel to the legacy home dashboard; no existing route is changed.
    Route::get('executive', [App\Http\Controllers\ExecutiveDashboardController::class, 'index'])->name('dashboard.executive');

    // R2.2 — Operations Command Center. Additive and parallel to the legacy /operations
    // workflow pages (Planning/Dispatch/Reconciliation/Exceptions); no existing route changed.
    Route::get('operations', [App\Http\Controllers\OperationsDashboardController::class, 'index'])->name('dashboard.operations');
});

// R4.5 — Business Intelligence dashboards (descriptive reports). Additive; read-only.
Route::group(['middleware' => ['auth'], 'prefix' => 'business', 'as' => 'business.'], function () {
    Route::get('executive', [App\Http\Controllers\BusinessDashboardController::class, 'executive'])->name('executive');
    Route::get('operations', [App\Http\Controllers\BusinessDashboardController::class, 'operations'])->name('operations');
    Route::get('fleet', [App\Http\Controllers\BusinessDashboardController::class, 'fleet'])->name('fleet');

    // R5.1 — BI report exports (HTML/CSV/JSON download). Read-only.
    Route::get('executive/export/{format}', [App\Http\Controllers\ExportController::class, 'executive'])->name('executive.export');
    Route::get('operations/export/{format}', [App\Http\Controllers\ExportController::class, 'operations'])->name('operations.export');
    Route::get('fleet/export/{format}', [App\Http\Controllers\ExportController::class, 'fleet'])->name('fleet.export');
});

Route::group(['middleware' => ['auth'], 'prefix' => 'settings'], function () {
    Route::get('fleet', [App\Http\Controllers\FleetSettingsController::class, 'edit'])->name('settings.fleet.edit');
    Route::put('fleet', [App\Http\Controllers\FleetSettingsController::class, 'update'])->name('settings.fleet.update');

    Route::get('operations-calendar', fn () => redirect('/planning/calendar'))->name('settings.operations-calendar.edit');
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

Route::group(['middleware' => ['auth'], 'prefix' => 'fuel'], function () {
    Route::get('', [App\Http\Controllers\FuelImportController::class, 'index'])->name('fuel.index');
    Route::get('export', [App\Http\Controllers\FuelImportController::class, 'export'])->name('fuel.export');
    Route::get('analytics', [App\Http\Controllers\FuelImportController::class, 'analytics'])->name('fuel.analytics');
    Route::get('edk/{recharge}', [App\Http\Controllers\FuelImportController::class, 'showEdk'])->name('fuel.edk.show');
    Route::get('fleeti/{record}', [App\Http\Controllers\FuelImportController::class, 'showFleeti'])->name('fuel.fleeti.show');

    Route::prefix('import')->group(function () {
        Route::get('', fn () => redirect()->route('fuel.index'))->name('fuel.import'); // legacy entry → workspace
        Route::post('edk/preview', [App\Http\Controllers\FuelImportController::class, 'previewEdk'])->name('fuel.import.edk.preview');
        Route::post('edk/commit', [App\Http\Controllers\FuelImportController::class, 'commitEdk'])->name('fuel.import.edk.commit');
        Route::post('fleeti/preview', [App\Http\Controllers\FuelImportController::class, 'previewFleeti'])->name('fuel.import.fleeti.preview');
        Route::post('fleeti/commit', [App\Http\Controllers\FuelImportController::class, 'commitFleeti'])->name('fuel.import.fleeti.commit');
    });

    // R10 — manual review workflow (pending transactions).
    Route::prefix('review')->group(function () {
        Route::get('', [App\Http\Controllers\FuelReviewController::class, 'queue'])->name('fuel.review');
        Route::get('{transaction}', [App\Http\Controllers\FuelReviewController::class, 'show'])->name('fuel.review.show');
        Route::post('{transaction}', [App\Http\Controllers\FuelReviewController::class, 'resolve'])->name('fuel.review.resolve');
    });
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
