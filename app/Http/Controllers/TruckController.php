<?php

namespace App\Http\Controllers;

use App\Exports\MaintenanceDueExport;
use App\Models\Maintenance;
use App\Models\LogisticsAlert;
use App\Models\Transporter;
use App\Models\Truck;
use App\Services\MaintenanceStatusService;
use App\Services\TruckMaintenanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TruckController extends Controller
{
    public function __construct(
        private readonly TruckMaintenanceService $truckMaintenanceService,
        private readonly MaintenanceStatusService $maintenanceStatusService
    ) {
        $this->middleware('permission:truck-list', ['only' => ['index', 'show', 'showPage']]);
        $this->middleware('permission:truck-create', ['only' => ['create', 'createPage', 'store']]);
        $this->middleware('permission:truck-edit', ['only' => ['edit', 'editPage', 'update']]);
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

        // Get active trucks with maintenance due (dynamically calculated)
        $maintenanceDueTrucks = $trucks->filter(fn($truck) => $truck->is_active && $truck->isMaintenanceDueByType());

        if ($request->ajax() && !$request->header('X-Inertia')) {
            // Add a sort_order column to preserve the ordering
            $trucks = $trucks->values()->map(function ($truck, $index) {
                $truck->sort_order = $index;
                return $truck;
            });

            return datatables()
                ->of($trucks)
                ->editColumn('transporter_id', function ($truck) {
                    return $truck->transporter->name ?? '';
                })
                ->addColumn('current_counter', function ($truck) {
                    if ($truck->usesKilometerMaintenance()) {
                        return number_format((float) $truck->total_kilometers, 0).' km';
                    }

                    return $truck->rotations_since_maintenance.' rotations';
                })
                ->addColumn('last_maintenance_counter', function ($truck) {
                    if ($truck->usesKilometerMaintenance()) {
                        return number_format($truck->lastMaintenanceKm(), 0).' km';
                    }

                    return '—';
                })
                ->addColumn('next_maintenance_counter', function ($truck) {
                    if ($truck->usesKilometerMaintenance()) {
                        return number_format($truck->nextMaintenanceAtKm(), 0).' km';
                    }

                    return Truck::MAX_ROTATIONS_BEFORE_MAINTENANCE.' rotations';
                })
                ->editColumn('maintenance_due', function ($truck) {
                    $remaining = $truck->maintenanceRemainingByType();
                    $level = $truck->maintenanceLevelByType();
                    $unit = $truck->maintenanceUnitByType();

                    $color = match ($level) {
                        'red' => 'danger',
                        'yellow' => 'warning',
                        default => 'success',
                    };

                    if ($level === 'red') {
                        $statusText = "<span class='badge bg-{$color}'>Maintenance Due</span>
                       <button class='btn btn-sm btn-primary ms-2'
                               data-id='{$truck->id}'
                                 onclick='showModal({
                                     title: \"Mark Maintenance for Truck . {$truck->matricule}\",
                                     route: \"".route('trucks.maintenances.create', $truck->id)."\",
                                     size: \"md\"
                                 })'
                               >
                           <i class='fa fa-wrench'></i>
                       </button>";
                    } else {
                        $statusText = "<span class='badge bg-{$color}'>{$remaining} {$unit} restantes</span>";
                    }

                    return $statusText;
                })
                ->addColumn('is_active', function ($truck) {
                    $isActive = $truck->is_active ?? true;
                    $badgeClass = $isActive ? 'bg-success' : 'bg-secondary';
                    $badgeText = $isActive ? 'Actif' : 'Inactif';
                    $buttonClass = $isActive ? 'btn-outline-danger' : 'btn-outline-success';
                    $buttonText = $isActive ? 'Désactiver' : 'Activer';
                    $buttonIcon = $isActive ? 'fa-ban' : 'fa-check';

                    return "
                        <span class='badge {$badgeClass}'>{$badgeText}</span>
                        <button class='btn btn-sm {$buttonClass} ms-2'
                                onclick='toggleTruckStatus({$truck->id})'>
                            <i class='fa {$buttonIcon}'></i> {$buttonText}
                        </button>
                    ";
                })
                ->addColumn('actions', function ($truck) {
                    $actions = [
                        [
                            'label' => 'Voir Détails',
                            'href' => route('trucks.show-page', $truck->id),
                            'permission' => true
                        ],
                        [
                            'label' => 'Modifier',
                            'href' => route('trucks.edit-page', $truck->id),
                            'permission' => true
                        ],
                        [
                            'label' => 'Checklist Rotation',
                            'onclick' => 'showModal({
                                title: "Checklist Maintenance - ' . $truck->matricule . '",
                                route: "' . route('trucks.maintenances.create', $truck->id) . '",
                                size: "md"
                            })',
                            'permission' => true
                        ],
                        [
                            'label' => 'Supprimer',
                            'onclick' => 'confirmDelete("' . route('trucks.destroy', $truck->id) . '")',
                            'permission' => true
                        ]
                    ];
                    return view('components.buttons.action', compact('actions'));
                })
                ->rawColumns(['actions', 'transporter_id', 'maintenance_due', 'is_active'])
                ->make(true);
        }

        $actions = [
            [
                'label' => 'Ajouter Camion',
                'url' => route('trucks.create-page'),
                'permission' => true
            ],
            [
                'label' => 'Changer Type Maintenance (Global)',
                'onclick' => 'return bulkUpdateMaintenanceType(event)',
                'permission' => true
            ],
            [
                'label' => 'Changer Intervalle KM (Global)',
                'onclick' => 'return bulkUpdateKmInterval(event)',
                'permission' => true
            ],
            [
                'label' => 'Exporter Maintenance (Excel)',
                'url' => route('trucks.maintenance.export-excel'),
                'permission' => true
            ],
            [
                'label' => 'Exporter Maintenance (PDF)',
                'url' => route('trucks.maintenance.export-pdf'),
                'permission' => true
            ],
            [
                'label' => 'Exporter Tous (Excel)',
                'url' => route('trucks.maintenance.export-excel', ['only_due' => false]),
                'permission' => true
            ],
            [
                'label' => 'Exporter Tous (PDF)',
                'url' => route('trucks.maintenance.export-pdf', ['only_due' => false]),
                'permission' => true
            ],
        ];

        return \Inertia\Inertia::render('trucks/Index', [
            'trucks' => $trucks->map(fn ($t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'transporter' => $t->transporter?->name,
                'maintenance_type' => $t->maintenance_type,
                'is_active' => $t->is_active,
                'total_kilometers' => $t->total_kilometers,
                'level' => $t->maintenanceLevelByType(),
                'remaining' => $t->maintenanceRemainingByType(),
                'unit' => $t->maintenanceUnitByType(),
            ])->values(),
            'maintenanceDueCount' => $maintenanceDueTrucks->count(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return $this->createPage();
    }

    public function createPage()
    {
        $transporters = Transporter::all()->map(fn ($t) => ['value' => $t->id, 'label' => $t->name]);
        return \Inertia\Inertia::render('trucks/Create', ['transporters' => $transporters]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'matricule' => 'required|string|max:255',
            'transporter_id' => 'required|exists:transporters,id',
            'km_maintenance_interval' => 'nullable|numeric|min:1',
        ]);

        $truck = Truck::firstOrCreate([
            'matricule' => $request->matricule,
            'transporter_id' => $request->transporter_id,
        ], [
            'km_maintenance_interval' => $request->km_maintenance_interval ?? Truck::MAX_KM_BEFORE_MAINTENANCE,
        ]);

        $this->truckMaintenanceService->updateMaintenanceProfileInterval(
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
    public function show(Truck $truck)
    {
        return $this->showPage($truck);
    }

    public function showPage(Truck $truck)
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

        return \Inertia\Inertia::render('trucks/Show', [
            'truck' => [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
                'transporter' => $truck->transporter?->name,
                'maintenance_type' => $truck->maintenance_type,
                'is_active' => $truck->is_active,
                'total_kilometers' => $truck->total_kilometers,
                'fleeti_id' => $truck->fleeti_id,
            ],
            'maintenanceInfo' => $maintenanceInfo,
            'recentTrackings' => $recentTrackings->map(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'driver' => $t->driver?->name,
                'provider' => $t->provider?->name,
                'provider_net_weight' => $t->provider_net_weight,
                'client_net_weight' => $t->client_net_weight,
                'client_date' => $t->client_date?->format('d/m/Y'),
            ]),
            'maintenances' => $maintenances->map(fn ($m) => [
                'id' => $m->id,
                'maintenance_date' => $m->maintenance_date,
                'maintenance_type' => $m->maintenance_type,
                'kilometers_at_maintenance' => $m->kilometers_at_maintenance,
                'notes' => $m->notes,
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Truck $truck)
    {
        return $this->editPage($truck);
    }

    public function editPage(Truck $truck)
    {
        $transporters = Transporter::all()->map(fn ($t) => ['value' => $t->id, 'label' => $t->name]);
        return \Inertia\Inertia::render('trucks/Edit', [
            'truck' => [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
                'transporter_id' => $truck->transporter_id,
                'maintenance_type' => $truck->maintenance_type,
                'km_maintenance_interval' => $truck->km_maintenance_interval,
                'is_active' => $truck->is_active,
            ],
            'transporters' => $transporters,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Truck $truck)
    {
        $request->validate([
            'matricule' => 'required|string|max:255',
            'transporter_id' => 'required|exists:transporters,id',
            'km_maintenance_interval' => 'nullable|numeric|min:1',
        ]);

        $truck->update([
            'matricule' => $request->matricule,
            'transporter_id' => $request->transporter_id,
            'km_maintenance_interval' => $request->km_maintenance_interval ?? $truck->km_maintenance_interval,
        ]);

        $this->truckMaintenanceService->updateMaintenanceProfileInterval(
            $truck->fresh(),
            Maintenance::TYPE_GENERAL,
            (float) ($request->km_maintenance_interval ?? $truck->km_maintenance_interval ?? Truck::MAX_KM_BEFORE_MAINTENANCE)
        );

        return redirect()
            ->route('trucks.index')
            ->with('success', 'Camion mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Truck $truck)
    {
        $truck->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Camion supprimé avec succès.',
        ]);
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

    public function updateMaintenanceProfileInterval(Request $request, Truck $truck)
    {
        return app(MaintenanceController::class)->updateProfileInterval($request, $truck);
    }
}
