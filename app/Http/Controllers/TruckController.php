<?php

namespace App\Http\Controllers;

use App\Exports\MaintenanceDueExport;
use App\Http\Controllers\Concerns\ResolvesPeriod;
use App\Models\Maintenance;
use App\Models\LogisticsAlert;
use App\Models\Transporter;
use App\Models\Truck;
use App\Services\FleetCapacityService;
use App\Services\FleetObjectiveService;
use App\Services\Fuel\FuelComparisonService;
use App\Services\MaintenanceStatusService;
use App\Services\ObjectiveHistoryService;
use App\Services\TruckKpiService;
use App\Services\TruckMaintenanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TruckController extends Controller
{
    use ResolvesPeriod;

    public function __construct(
        private readonly TruckMaintenanceService $truckMaintenanceService,
        private readonly MaintenanceStatusService $maintenanceStatusService,
        private readonly TruckKpiService $kpiService,
        private readonly FuelComparisonService $fuelComparison,
        private readonly ObjectiveHistoryService $objectiveHistory,
        private readonly FleetObjectiveService $fleetObjectives,
        private readonly FleetCapacityService $fleetCapacity,
    ) {
        $this->middleware('permission:truck-list', ['only' => ['index', 'show', 'showPage']]);
        $this->middleware('permission:truck-create', ['only' => ['store']]);
        $this->middleware('permission:truck-edit', ['only' => ['update']]);
        $this->middleware('permission:truck-delete', ['only' => ['destroy']]);
        $this->middleware('permission:maintenance-create', ['only' => ['createMaintenance', 'storeMaintenance', 'bulkStoreMaintenance']]);
    }

    private function currentUserIsLogisticsManager(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['Admin', 'Super Admin']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $trucks = Truck::query()
            ->with('transporter')
            ->orderByDesc('is_active')
            ->orderBy('matricule')
            ->get();

        $maintenanceDueTrucks = $trucks->filter(fn ($truck) => $truck->is_active && $truck->isMaintenanceDueByType());

        return \Inertia\Inertia::render('trucks/Index', [
            'trucks' => $trucks->map(fn ($t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'transporter' => $t->transporter?->name,
                'transporter_id' => $t->transporter_id,
                'maintenance_type' => $t->maintenance_type,
                'km_maintenance_interval' => $t->km_maintenance_interval !== null ? (float) $t->km_maintenance_interval : null,
                'target_rotations_per_week' => $t->target_rotations_per_week,
                'is_active' => (bool) $t->is_active,
                'is_available' => (bool) $t->is_available,
                'total_kilometers' => (float) $t->total_kilometers,
                'fleeti_last_fuel_level' => $t->fleeti_last_fuel_level !== null ? (float) $t->fleeti_last_fuel_level : null,
                'level' => $t->maintenanceLevelByType(),
                'remaining' => $t->maintenanceRemainingByType(),
                'unit' => $t->maintenanceUnitByType(),
            ])->values(),
            'maintenanceDueCount' => $maintenanceDueTrucks->count(),
            'transporters' => Transporter::orderBy('name')->get()->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->values(),
            'defaultTargetRotationsPerWeek' => $this->fleetCapacity->defaultTargetRotationsPerWeek(),
            'defaultCapacityTonnage' => $this->fleetCapacity->defaultCapacityTonnage(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'matricule' => 'required|string|max:255',
            'transporter_id' => 'required|exists:transporters,id',
            'km_maintenance_interval' => 'nullable|numeric|min:1',
            'target_rotations_per_week' => 'nullable|integer|min:1|max:14',
            'is_available' => 'nullable|boolean',
        ];

        // Capacity is a single fleet-wide setting (Paramètres flotte), not a
        // per-truck value — so changing it there changes it everywhere.
        $globalCapacity = $this->fleetCapacity->defaultCapacityTonnage();

        if ($request->filled('target_rotations_per_week')) {
            $rules['change_note'] = 'required|string|min:5|max:1000';
        }

        $request->validate($rules);

        $truck = Truck::firstOrCreate([
            'matricule' => $request->matricule,
            'transporter_id' => $request->transporter_id,
        ], [
            'km_maintenance_interval' => $request->km_maintenance_interval ?? Truck::MAX_KM_BEFORE_MAINTENANCE,
            'capacity_tonnage' => $globalCapacity,
            'target_rotations_per_week' => $request->target_rotations_per_week,
            'is_available' => $request->has('is_available') ? (bool) $request->boolean('is_available') : true,
        ]);

        $oldTargetRotations = $truck->target_rotations_per_week;

        // Keep capacity aligned with the fleet setting even for an existing row.
        $truck->update(['capacity_tonnage' => $globalCapacity]);
        if ($request->has('target_rotations_per_week')) {
            $truck->update(['target_rotations_per_week' => $request->filled('target_rotations_per_week') ? (int) $request->target_rotations_per_week : null]);
        }
        if ($request->has('is_available')) {
            $truck->update(['is_available' => (bool) $request->boolean('is_available')]);
        }

        $note = $request->input('change_note');
        if ($note) {
            $this->objectiveHistory->record(
                subject: $truck,
                subjectLabel: 'Camion ' . $truck->matricule,
                fieldName: 'target_rotations_per_week',
                fieldLabel: 'Rotations/semaine cible (override)',
                oldValue: $oldTargetRotations,
                newValue: $truck->target_rotations_per_week,
                note: $note,
                context: ['scope' => 'truck_create'],
            );
        }

        $this->truckMaintenanceService->replaceMaintenanceProfileInterval(
            $truck->fresh(),
            Maintenance::TYPE_GENERAL,
            (float) ($request->km_maintenance_interval ?? $truck->km_maintenance_interval ?? Truck::MAX_KM_BEFORE_MAINTENANCE)
        );

        return redirect()
            ->route('trucks.index')
            ->with('success', 'Camion créé avec succès.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Truck $truck, Request $request)
    {
        return $this->showPage($truck, $request);
    }

    public function showPage(Truck $truck, Request $request)
    {
        $this->maintenanceStatusService->recalculateForTruck($truck);

        $truck->load([
            'transporter',
            'maintenances' => fn($q) => $q->latest('maintenance_date'),
            'transportTrackings' => fn($q) => $q->latest('client_date')->with(['driver', 'provider'])->limit(20),
        ]);

        $maintenanceInfo = $truck->getMaintenanceInfo();
        $recentTrackings = $truck->transportTrackings;
        $maintenances = $truck->maintenances;

        [$from, $to, $preset] = $this->resolvePeriod($request);
        $kpi = $this->kpiService->compute($truck, $from, $to);
        $fuelComparison = $this->fuelComparison->forTruck($truck, 12);

        return \Inertia\Inertia::render('trucks/Show', [
            'truck' => [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
                'transporter' => $truck->transporter?->name,
                'maintenance_type' => $truck->maintenance_type,
                'is_active' => $truck->is_active,
                'is_available' => (bool) $truck->is_available,
                'total_kilometers' => $truck->total_kilometers,
                'km_maintenance_interval' => $truck->km_maintenance_interval,
                'has_gps' => ! empty($truck->fleeti_asset_id),
                'fleeti_last_kilometers' => $truck->fleeti_last_kilometers,
                'fleeti_last_fuel_level' => $truck->fleeti_last_fuel_level,
                'fleeti_last_synced_at' => $truck->fleeti_last_synced_at?->format('d/m/Y H:i'),
            ],
            'maintenanceInfo' => $maintenanceInfo,
            'recentTrackings' => $recentTrackings->map(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'driver' => $t->driver?->name,
                'provider' => $t->provider?->name,
                'provider_net_weight' => $t->provider_net_weight,
                'client_net_weight' => $t->client_net_weight,
                'gap' => $t->gap,
                'client_date' => $t->client_date?->format('d/m/Y'),
                'provider_date' => $t->provider_date?->format('d/m/Y'),
            ]),
            'maintenances' => $maintenances->map(fn ($m) => [
                'id' => $m->id,
                'maintenance_date' => $m->maintenance_date?->format('d/m/Y'),
                'maintenance_type' => $m->maintenance_type,
                'kilometers_at_maintenance' => $m->kilometers_at_maintenance,
                'trigger_km' => $m->trigger_km,
                'notes' => $m->notes,
            ]),
            'kpi' => $kpi,
            'fuelComparison' => $fuelComparison,
            'filter' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'preset' => $preset,
            ],
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Truck $truck)
    {
        $oldTargetRotations = $truck->target_rotations_per_week;
        $oldAvailable = (bool) $truck->is_available;

        // Capacity is owned by the fleet setting, never edited per truck — always
        // realign to the global value so it stays consistent everywhere.
        $globalCapacity = $this->fleetCapacity->defaultCapacityTonnage();
        $newTargetRotations = $request->filled('target_rotations_per_week')
            ? (int) $request->target_rotations_per_week
            : null;

        $targetRotationsChanged = (string) ($newTargetRotations ?? '') !== (string) ($oldTargetRotations ?? '');

        $rules = [
            'matricule' => 'required|string|max:255',
            'transporter_id' => 'required|exists:transporters,id',
            'km_maintenance_interval' => 'nullable|numeric|min:1',
            'target_rotations_per_week' => 'nullable|integer|min:1|max:14',
            'is_available' => 'nullable|boolean',
        ];
        if ($targetRotationsChanged) {
            $rules['change_note'] = 'required|string|min:5|max:1000';
        }
        $request->validate($rules);

        $truck->update([
            'matricule' => $request->matricule,
            'transporter_id' => $request->transporter_id,
            'km_maintenance_interval' => $request->km_maintenance_interval ?? $truck->km_maintenance_interval,
            'capacity_tonnage' => $globalCapacity,
            'target_rotations_per_week' => $newTargetRotations,
            'is_available' => $request->has('is_available') ? (bool) $request->boolean('is_available') : (bool) $truck->is_available,
        ]);

        $note = $request->input('change_note');
        if ($note && $targetRotationsChanged) {
            $this->objectiveHistory->record(
                subject: $truck,
                subjectLabel: 'Camion ' . $truck->matricule,
                fieldName: 'target_rotations_per_week',
                fieldLabel: 'Rotations/semaine cible (override)',
                oldValue: $oldTargetRotations,
                newValue: $truck->target_rotations_per_week,
                note: $note,
                context: ['scope' => 'truck_update'],
            );
        }

        $this->truckMaintenanceService->replaceMaintenanceProfileInterval(
            $truck->fresh(),
            Maintenance::TYPE_GENERAL,
            (float) ($request->km_maintenance_interval ?? $truck->km_maintenance_interval ?? Truck::MAX_KM_BEFORE_MAINTENANCE)
        );

        // Availability feeds the objective distribution — redistribute current/
        // future objectives so the plan tracks the truck's new state.
        if ((bool) $truck->is_available !== $oldAvailable) {
            $this->fleetObjectives->redistributeOpenObjectives();
        }

        return redirect()
            ->route('trucks.show-page', $truck)
            ->with('success', 'Camion mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Truck $truck)
    {
        $truck->delete();

        return redirect()->back()->with('success', 'Camion supprimé avec succès.');
    }

    // Maintenance methods delegate to MaintenanceController for backward compatibility
    public function createMaintenance(Truck $truck)
    {
        return app(MaintenanceController::class)->create($truck);
    }

    public function storeMaintenance(Request $request, $truckId)
    {
        return app(MaintenanceController::class)->store($request, $truckId);
    }

    public function maintenanceDue()
    {
        return app(MaintenanceController::class)->due();
    }

    public function exportMaintenanceExcel(Request $request)
    {
        return app(MaintenanceController::class)->exportExcel($request);
    }

    public function exportMaintenancePdf(Request $request)
    {
        return app(MaintenanceController::class)->exportPdf($request);
    }

    public function toggleAvailability(Truck $truck)
    {
        $truck->update([
            'is_available' => ! $truck->is_available,
        ]);

        $status = $truck->is_available ? 'marqué disponible' : 'marqué indisponible';

        // Availability drives the objective plan: redistribute current/future
        // objectives across the trucks now in service.
        $touched = $this->fleetObjectives->redistributeOpenObjectives();
        $msg = "Camion {$truck->matricule} {$status}.";
        if ($touched > 0) {
            $msg .= ' Objectif(s) en cours redistribué(s) sur les camions en service.';
        }

        return back()->with('success', $msg);
    }

    public function toggleActive(Truck $truck)
    {
        $truck->update([
            'is_active' => !$truck->is_active,
        ]);

        $status = $truck->is_active ? 'activé' : 'désactivé';

        return response()->json([
            'status' => 'success',
            'message' => "Camion {$truck->matricule} {$status} avec succès.",
            'is_active' => $truck->is_active,
        ]);
    }

    /**
     * Bulk store maintenance for multiple trucks
     */
    public function bulkStoreMaintenance(Request $request)
    {
        return app(MaintenanceController::class)->bulkStore($request);
    }

    public function updateMaintenanceType(Request $request, Truck $truck)
    {
        return app(MaintenanceController::class)->updateType($request, $truck);
    }

    public function bulkUpdateMaintenanceType(Request $request)
    {
        return app(MaintenanceController::class)->bulkUpdateType($request);
    }

    public function bulkUpdateKmInterval(Request $request)
    {
        return app(MaintenanceController::class)->bulkUpdateKmInterval($request);
    }

    public function replaceMaintenanceProfileInterval(Request $request, Truck $truck)
    {
        return app(MaintenanceController::class)->updateProfileInterval($request, $truck);
    }

    /**
     * Live fleet map page — lists every active truck with its last known
     * telemetry cache (no extra joins, everything is on the trucks table).
     */
    public function mapPage()
    {
        $trucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get([
                'id',
                'matricule',
                'fleeti_last_latitude',
                'fleeti_last_longitude',
                'fleeti_last_heading_deg',
                'fleeti_last_speed_kmh',
                'fleeti_last_movement_status',
                'fleeti_last_ignition_on',
                'fleeti_last_fuel_level',
                'fleeti_last_synced_at',
            ])
            ->map(fn (Truck $t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'latitude' => $t->fleeti_last_latitude !== null ? (float) $t->fleeti_last_latitude : null,
                'longitude' => $t->fleeti_last_longitude !== null ? (float) $t->fleeti_last_longitude : null,
                'heading' => $t->fleeti_last_heading_deg !== null ? (float) $t->fleeti_last_heading_deg : null,
                'speed' => $t->fleeti_last_speed_kmh !== null ? (float) $t->fleeti_last_speed_kmh : null,
                'movement_status' => $t->fleeti_last_movement_status,
                'ignition_on' => $t->fleeti_last_ignition_on,
                'fuel_level' => $t->fleeti_last_fuel_level !== null ? (float) $t->fleeti_last_fuel_level : null,
                'last_sync' => $t->fleeti_last_synced_at?->format('d/m/Y H:i'),
            ])
            ->filter(fn ($t) => $t['latitude'] !== null && $t['longitude'] !== null)
            ->values()
            ->all();

        return \Inertia\Inertia::render('logistics/FleetMap', [
            'trucks' => $trucks,
        ]);
    }
}
