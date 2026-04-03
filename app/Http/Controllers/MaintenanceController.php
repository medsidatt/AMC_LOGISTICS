<?php

namespace App\Http\Controllers;

use App\Exports\MaintenanceDueExport;
use App\Models\LogisticsAlert;
use App\Models\Maintenance;
use App\Models\Transporter;
use App\Models\Truck;
use App\Services\MaintenanceStatusService;
use App\Services\TruckMaintenanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MaintenanceController extends Controller
{
    public function __construct(
        private readonly TruckMaintenanceService $truckMaintenanceService,
        private readonly MaintenanceStatusService $maintenanceStatusService
    ) {
        $this->middleware('permission:maintenance-create');
    }

    public function create(Truck $truck)
    {
        $this->authorizeLogisticsManager();
        return view('pages.trucks.maintenances.create', compact('truck'));
    }

    public function store(Request $request, $truckId): JsonResponse
    {

        $request->validate([
            'notes' => 'nullable|string',
            'date' => 'required|date',
            'maintenance_type' => 'nullable|string',
            'kilometers_at_maintenance' => 'required|numeric|min:0',
        ]);

        $truck = Truck::findOrFail($truckId);
        $maintenanceType = $request->maintenance_type ?? Maintenance::TYPE_GENERAL;

        if ($truck->hasMaintenanceOnDate($request->date, $maintenanceType)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Une maintenance de ce type existe déjà pour cette date.',
            ], 422);
        }

        $truck->markMaintenanceDone(
            $request->date,
            $request->notes,
            $maintenanceType,
            (float) $request->kilometers_at_maintenance
        );

        if ($maintenanceType === Maintenance::TYPE_OIL) {
            LogisticsAlert::query()
                ->where('type', 'due_engine')
                ->where('truck_id', $truck->id)
                ->whereDate('checklist_date', $request->date)
                ->update(['resolved_at' => now()]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Maintenance enregistrée avec succès.',
            'redirectRoute' => route('trucks.index')
        ]);
    }

    public function due(): JsonResponse
    {

        $trucks = Truck::with(['maintenances' => fn($q) => $q->latest('maintenance_date')])
            ->get()
            ->filter(fn($truck) => $truck->isMaintenanceDueByType())
            ->map(function ($truck) {
                return [
                    'id' => $truck->id,
                    'matricule' => $truck->matricule,
                    'maintenance_type' => $truck->maintenance_type,
                    'last_maintenance_date' => $truck->last_maintenance_date,
                    'counter_since_maintenance' => $truck->maintenanceCounterByType(),
                    'counter_unit' => $truck->maintenanceUnitByType(),
                    'maintenance_level' => $truck->maintenanceLevelByType(),
                ];
            })
            ->values();

        return response()->json($trucks);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'truck_ids' => 'required|array|min:1',
            'truck_ids.*' => 'exists:trucks,id',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $successCount = 0;
        $skippedCount = 0;

        foreach ($request->truck_ids as $truckId) {
            $truck = Truck::find($truckId);

            if (!$truck || !$truck->is_active) {
                $skippedCount++;
                continue;
            }

            if ($truck->hasMaintenanceOnDate($request->date, Maintenance::TYPE_GENERAL)) {
                $skippedCount++;
                continue;
            }

            $truck->markMaintenanceDone($request->date, $request->notes, Maintenance::TYPE_GENERAL);
            $successCount++;
        }

        if ($successCount === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune maintenance appliquée. Tous les camions ont déjà une maintenance à cette date.',
            ], 422);
        }

        $message = "Maintenance appliquée à {$successCount} camion(s) avec succès.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} camion(s) ignoré(s) (maintenance déjà existante ou inactif).";
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'success_count' => $successCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function exportExcel(Request $request)
    {

        $onlyDue = filter_var($request->get('only_due', true), FILTER_VALIDATE_BOOLEAN);

        $filename = $onlyDue
            ? 'maintenance-requise-' . now()->format('Y-m-d') . '.xlsx'
            : 'etat-maintenance-camions-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new MaintenanceDueExport($onlyDue), $filename);
    }

    public function exportPdf(Request $request)
    {

        $onlyDue = filter_var($request->get('only_due', true), FILTER_VALIDATE_BOOLEAN);

        $transporter = Transporter::where('name', 'like', '%AMC Travaux SN SARL%')->first();

        $query = Truck::with(['transporter', 'maintenances' => fn($q) => $q->latest('maintenance_date')]);

        if ($transporter) {
            $query->where('transporter_id', $transporter->id);
        }

        $query->where('is_active', true)->orderBy('matricule');

        $allTrucks = $query->get();

        $trucks = $onlyDue
            ? $allTrucks->filter(fn($truck) => $truck->maintenance_type === 'kilometers' ? $truck->km_maintenance_due : $truck->maintenance_due)
            : $allTrucks;

        $totalTrucks = $allTrucks->count();
        $maintenanceDueCount = $allTrucks->filter(fn($truck) => $truck->maintenance_type === 'kilometers' ? $truck->kmMaintenanceLevel() === 'red' : $truck->maintenanceLevel() === 'red')->count();
        $warningCount = $allTrucks->filter(fn($truck) => $truck->maintenance_type === 'kilometers' ? $truck->kmMaintenanceLevel() === 'yellow' : $truck->maintenanceLevel() === 'yellow')->count();
        $okCount = $allTrucks->filter(fn($truck) => $truck->maintenance_type === 'kilometers' ? $truck->kmMaintenanceLevel() === 'green' : $truck->maintenanceLevel() === 'green')->count();

        $pdf = Pdf::loadView('pages.trucks.exports.maintenance-due-pdf', [
            'trucks' => $trucks,
            'onlyDue' => $onlyDue,
            'totalTrucks' => $totalTrucks,
            'maintenanceDueCount' => $maintenanceDueCount,
            'warningCount' => $warningCount,
            'okCount' => $okCount,
            'transporterName' => $transporter?->name ?? 'AMC Travaux SN SARL',
        ]);

        $filename = $onlyDue
            ? 'maintenance-requise-' . now()->format('Y-m-d') . '.pdf'
            : 'etat-maintenance-camions-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function updateType(Request $request, Truck $truck): JsonResponse
    {
        $request->validate([
            'maintenance_type' => 'required|string|in:rotations,kilometers',
        ]);

        $this->truckMaintenanceService->updateMaintenanceType($truck, $request->maintenance_type);

        return response()->json([
            'status' => 'success',
            'message' => 'Type de maintenance mis à jour avec succès.',
        ]);
    }

    public function bulkUpdateType(Request $request): JsonResponse
    {
        $request->validate([
            'maintenance_type' => 'required|string|in:rotations,kilometers',
        ]);

        $this->truckMaintenanceService->bulkUpdateMaintenanceType($request->maintenance_type);

        return response()->json([
            'status' => 'success',
            'message' => 'Type de maintenance mis à jour pour tous les camions avec succès.',
        ]);
    }

    public function bulkUpdateKmInterval(Request $request): JsonResponse
    {
        $request->validate([
            'km_maintenance_interval' => 'required|numeric|min:1',
        ]);

        $this->truckMaintenanceService->bulkUpdateKmInterval((float) $request->km_maintenance_interval);

        return response()->json([
            'status' => 'success',
            'message' => 'Intervalle de maintenance KM mis à jour pour tous les camions avec succès.',
        ]);
    }

    public function updateProfileInterval(Request $request, Truck $truck): JsonResponse
    {
        $data = $request->validate([
            'maintenance_type' => 'required|string',
            'interval_km' => 'required|numeric|min:1',
            'warning_threshold_km' => 'nullable|numeric|min:0',
        ]);

        $this->truckMaintenanceService->updateMaintenanceProfileInterval(
            $truck,
            $data['maintenance_type'],
            (float) $data['interval_km'],
            isset($data['warning_threshold_km']) ? (float) $data['warning_threshold_km'] : null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Profil de maintenance mis à jour avec succès.',
        ]);
    }
}
