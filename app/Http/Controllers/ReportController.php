<?php

namespace App\Http\Controllers;

use App\Exports\FleetReport;
use App\Exports\IdleHourlyReportExport;
use App\Exports\MaintenanceDueExport;
use App\Exports\MaintenanceHistoryExport;
use App\Exports\TransportTrackingExport;
use App\Models\Truck;
use App\Services\IdleHourlyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    // ── Idle Hourly (engine on, not moving) ──

    public function idleHourly()
    {
        $trucks = Truck::query()
            ->orderBy('matricule')
            ->get(['id', 'matricule'])
            ->map(fn ($t) => ['id' => $t->id, 'matricule' => $t->matricule]);

        return Inertia::render('reports/IdleHourly', [
            'trucks' => $trucks,
        ]);
    }

    public function idleHourlyData(Request $request, IdleHourlyReportService $service): JsonResponse
    {
        $data = $request->validate([
            'truck_ids' => ['required', 'array', 'min:1'],
            'truck_ids.*' => ['integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        $rows = $service->build($data['truck_ids'], $from, $to);

        return response()->json(['rows' => $rows]);
    }

    public function exportIdleHourlyExcel(Request $request)
    {
        $filters = [
            'truck_ids' => $request->input('truck_ids', []),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];
        $name = 'idle-horaire-' . now()->format('d-m-Y') . '.xlsx';
        return Excel::download(new IdleHourlyReportExport($filters), $name);
    }
}
