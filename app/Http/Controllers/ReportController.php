<?php

namespace App\Http\Controllers;

use App\Exports\FleetReport;
use App\Exports\MaintenanceDueExport;
use App\Exports\MaintenanceHistoryExport;
use App\Exports\TransportTrackingExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return Inertia::render('reports/Index');
    }

    // ── Transport Tracking ──

    public function exportTransportExcel(Request $request)
    {
        $filters = $request->only(['truck_id', 'driver_id', 'provider_id', 'transporter_id', 'product', 'from', 'to']);
        $name = 'suivi-transport-' . now()->format('d-m-Y') . '.xlsx';
        return Excel::download(new TransportTrackingExport($filters), $name);
    }


    // ── Fleet ──

    public function exportFleetExcel(Request $request)
    {
        $activeOnly = $request->boolean('active_only', true);
        return Excel::download(new FleetReport($activeOnly), 'flotte-' . now()->format('d-m-Y') . '.xlsx');
    }

    // ── Maintenance ──

    public function exportMaintenanceExcel(Request $request)
    {
        $filters = $request->only(['truck_id', 'maintenance_type', 'from', 'to']);
        return Excel::download(new MaintenanceHistoryExport($filters), 'maintenance-' . now()->format('d-m-Y') . '.xlsx');
    }

    public function exportMaintenanceDueExcel(Request $request)
    {
        $onlyDue = $request->boolean('only_due', true);
        return Excel::download(new MaintenanceDueExport($onlyDue), 'maintenance-requise-' . now()->format('d-m-Y') . '.xlsx');
    }
}
