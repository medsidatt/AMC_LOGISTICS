<?php

use App\Http\Controllers\DriverController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\TrackingDashboardController;
use App\Http\Controllers\TransporterController;
use App\Http\Controllers\TransportTrackingController;
use App\Http\Controllers\TruckController;
use App\Http\Controllers\LogisticsManagerController;
use App\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

// /----- Providers Routes -----/
Route::group(['prefix' => 'providers', 'as' => 'providers.', 'middleware' => ['auth']], function () {
    Route::get('/', [ProviderController::class, 'index'])->name('index');
    Route::get('/{provider}/show', [ProviderController::class, 'show'])->name('show');
    Route::get('/create', [ProviderController::class, 'create'])->name('create');
    Route::post('/store', [ProviderController::class, 'store'])->name('store');
    Route::get('/{provider}/edit', [ProviderController::class, 'edit'])->name('edit');
    Route::put('/{provider}/update', [ProviderController::class, 'update'])->name('update');
    Route::delete('/{provider}/destroy', [ProviderController::class, 'destroy'])->name('destroy');
});

// ----- Transporters Routes -----/
Route::group(['prefix' => 'transporters', 'as' => 'transporters.', 'middleware' => ['auth']], function () {
    Route::get('/', [TransporterController::class, 'index'])->name('index');
    Route::get('/{transporter}/show', [TransporterController::class, 'show'])->name('show');
    Route::get('/create', [TransporterController::class, 'create'])->name('create');
    Route::post('/store', [TransporterController::class, 'store'])->name('store');
    Route::get('/{transporter}/edit', [TransporterController::class, 'edit'])->name('edit');
    Route::put('/{transporter}/update', [TransporterController::class, 'update'])->name('update');
    Route::delete('/{transporter}/destroy', [TransporterController::class, 'destroy'])->name('destroy');
});

// ----- Drivers Routes -----/
Route::group(['prefix' => 'drivers', 'as' => 'drivers.', 'middleware' => ['auth']], function () {
    // Driver self-service (own data only)
    Route::get('/my-trips', [DriverController::class, 'myTrips'])->name('my-trips');
    Route::get('/my-truck', [DriverController::class, 'myTruck'])->name('my-truck');
    Route::get('/checklist-page', [DriverController::class, 'checklistPage'])->name('checklist-page');
    Route::post('/checklist-submit', [DriverController::class, 'submitChecklist'])->name('checklist-submit');

    // Admin management
    Route::get('/', [DriverController::class, 'index'])->name('index');
    Route::get('/{driver}/show-page', [DriverController::class, 'showPage'])->name('show-page');
    Route::get('/{driver}/show', [DriverController::class, 'show'])->name('show');
    Route::get('/create', [DriverController::class, 'create'])->name('create');
    Route::post('/store', [DriverController::class, 'store'])->name('store');
    Route::get('/{driver}/edit', [DriverController::class, 'edit'])->name('edit');
    Route::put('/{driver}/update', [DriverController::class, 'update'])->name('update');
    Route::delete('/{driver}/destroy', [DriverController::class, 'destroy'])->name('destroy');
});

// ----- Trucks Routes -----/
Route::group(['prefix' => 'trucks', 'as' => 'trucks.', 'middleware' => ['auth']], function () {
    Route::get('/', [TruckController::class, 'index'])->name('index');
    Route::get('/create-page', [TruckController::class, 'createPage'])->name('create-page');
    Route::get('/{truck}/show-page', [TruckController::class, 'showPage'])->name('show-page');
    Route::get('/{truck}/show', [TruckController::class, 'show'])->name('show');
    Route::get('/create', [TruckController::class, 'create'])->name('create');
    Route::get('/{truck}/edit-page', [TruckController::class, 'editPage'])->name('edit-page');
    Route::post('/store', [TruckController::class, 'store'])->name('store');
    Route::get('/{truck}/edit', [TruckController::class, 'edit'])->name('edit');
    Route::put('/{truck}/update', [TruckController::class, 'update'])->name('update');
    Route::delete('/{truck}/destroy', [TruckController::class, 'destroy'])->name('destroy');

    // Maintenance
    Route::get('/{truck}/maintenances/create', [TruckController::class, 'createMaintenance'])->name('maintenances.create');
    Route::post('/{truck}/maintenances/store', [TruckController::class, 'storeMaintenance'])->name('maintenances.store');
    Route::post('/maintenances/bulk-store', [TruckController::class, 'bulkStoreMaintenance'])->name('maintenances.bulk-store');
    Route::get('/trucks/maintenance-due', [TruckController::class, 'maintenanceDue'])->name('maintenance-due');

    // Export maintenance reports
    Route::get('/maintenance/export-excel', [TruckController::class, 'exportMaintenanceExcel'])->name('maintenance.export-excel');
    Route::get('/maintenance/export-pdf', [TruckController::class, 'exportMaintenancePdf'])->name('maintenance.export-pdf');

    // Toggle truck active status
    Route::post('/{truck}/toggle-active', [TruckController::class, 'toggleActive'])->name('toggle-active');

    // Bulk maintenance operations
    Route::post('/bulk-update-maintenance-type', [TruckController::class, 'bulkUpdateMaintenanceType'])->name('bulk-update-maintenance-type');
    Route::post('/bulk-update-km-interval', [TruckController::class, 'bulkUpdateKmInterval'])->name('bulk-update-km-interval');
    Route::post('/{truck}/maintenance-profiles/update-interval', [TruckController::class, 'updateMaintenanceProfileInterval'])->name('maintenance-profiles.update-interval');
    Route::post('/{truck}/update-maintenance-type', [TruckController::class, 'updateMaintenanceType'])->name('update-maintenance-type');
});

// ----- Transport_tracking Routes -----/
Route::group(['prefix' => 'transport_tracking', 'as' => 'transport_tracking.', 'middleware' => ['auth']], function () {
    Route::get('/', [TransportTrackingController::class, 'index'])->name('index');
    Route::get('/create-page', [TransportTrackingController::class, 'createPage'])->name('create-page');
    Route::get('/{transport_tracking}/show-page', [TransportTrackingController::class, 'showPage'])->name('show-page');
    Route::get('/create', [TransportTrackingController::class, 'create'])->name('create');
    Route::get('/import', [TransportTrackingController::class, 'import'])->name('import');
    Route::post('/import', [TransportTrackingController::class, 'import'])->name('import');
    Route::post('/store', [TransportTrackingController::class, 'store'])->name('store');
    Route::get('/show/{transport_tracking}', [TransportTrackingController::class, 'show'])->name('show');

    Route::delete('/{id}/document/{documentId}', [TransportTrackingController::class, 'deleteDocument'])->name('document.delete');

    Route::get('/{transport_tracking}/edit-page', [TransportTrackingController::class, 'editPage'])->name('edit-page');
    Route::get('/{transport_tracking}/edit', [TransportTrackingController::class, 'edit'])->name('edit');
    Route::put('/{transport_tracking}/update', [TransportTrackingController::class, 'update'])->name('update');
    Route::delete('/{transport_tracking}/destroy', [TransportTrackingController::class, 'destroy'])->name('destroy');

    Route::get('/file/{id}', [TransportTrackingController::class, 'openCombinedPDF'])->name('file');
    Route::get('/file-page/{id}', [TransportTrackingController::class, 'filePage'])->name('file-page');
    Route::get('/preview-files/{id}', [TransportTrackingController::class, 'previewFiles'])->name('preview-files');

    Route::get('/ask-ai', [TransportTrackingController::class, 'askAI'])->name('ask-ai.get');
    Route::post('/ask-ai', [TransportTrackingController::class, 'analyze'])->name('ask-ai');
    Route::post('/analyze-all', [TransportTrackingController::class, 'analyzeAll'])->name('analyze-all');

    Route::get('/dashboard', [TransportTrackingController::class, 'dashboard'])->name('dashboard');
    Route::get('/export', [TransportTrackingController::class, 'export'])->name('export');
    Route::get('/export-missing', [TransportTrackingController::class, 'exportMissing'])->name('export-missing');
});

// ----- Logistics Manager Routes -----
Route::group(['prefix' => 'logistics', 'as' => 'logistics.', 'middleware' => ['auth']], function () {
    Route::get('/dashboard', [LogisticsManagerController::class, 'dashboard'])->name('dashboard');
    Route::get('/reports', [LogisticsManagerController::class, 'reports'])->name('reports');
    Route::post('/daily-issues/{issue}/resolve', [LogisticsManagerController::class, 'resolveDailyIssue'])->name('daily-issues.resolve');
    Route::post('/rotations/{transportTracking}/validate', [LogisticsManagerController::class, 'validateRotation'])->name('rotations.validate');
});

// ----- Maintenance Routes -----
Route::group(['prefix' => 'maintenance', 'as' => 'maintenance.', 'middleware' => ['auth']], function () {
    Route::get('/', [MaintenanceController::class, 'index'])->name('index');
    Route::get('/rules', [MaintenanceController::class, 'rules'])->name('rules');
    Route::post('/rules', [MaintenanceController::class, 'storeRule'])->name('rules.store');
    Route::post('/rules/{profile}/deactivate', [MaintenanceController::class, 'deactivateRule'])->name('rules.deactivate');
    Route::post('/{truck}/record', [MaintenanceController::class, 'recordMaintenance'])->name('record');
    Route::get('/history', [MaintenanceController::class, 'history'])->name('history');
});

Route::middleware('auth')->get('/dashboard/trackings', [TrackingDashboardController::class, 'index'])->name('dashboard.trackings');
Route::middleware('auth')->get('/dashboard/fleeti', [TrackingDashboardController::class, 'fleeti'])->name('dashboard.fleeti');
Route::middleware('auth')->get('/dashboard/rotations', [TrackingDashboardController::class, 'rotations'])->name('dashboard.rotations');

// ── Reports ──
Route::group(['prefix' => 'reports', 'as' => 'reports.', 'middleware' => ['auth']], function () {
    Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('index');
    Route::get('/transport/excel', [\App\Http\Controllers\ReportController::class, 'exportTransportExcel'])->name('transport.excel');
    Route::get('/fleet/excel', [\App\Http\Controllers\ReportController::class, 'exportFleetExcel'])->name('fleet.excel');
    Route::get('/maintenance/excel', [\App\Http\Controllers\ReportController::class, 'exportMaintenanceExcel'])->name('maintenance.excel');
    Route::get('/maintenance-due/excel', [\App\Http\Controllers\ReportController::class, 'exportMaintenanceDueExcel'])->name('maintenance-due.excel');
});
