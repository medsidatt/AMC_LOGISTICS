<?php

namespace App\Http\Controllers;

use App\Exports\MaintenanceDueExport;
use App\Models\InspectionChecklistIssue;
use App\Models\LogisticsAlert;
use App\Models\Maintenance;
use App\Models\Transporter;
use App\Models\Truck;
use App\Models\TruckMaintenanceProfile;
use App\Services\MaintenanceStatusService;
use App\Services\SharePointStorageService;
use App\Services\TruckMaintenanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MaintenanceController extends Controller
{
    public function __construct(
        private readonly TruckMaintenanceService $truckMaintenanceService,
        private readonly MaintenanceStatusService $maintenanceStatusService,
        private readonly SharePointStorageService $sharePointStorage
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

        $this->truckMaintenanceService->replaceMaintenanceProfileInterval(
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

    // ── New Inertia pages ──

    public function index()
    {
        // Count open issues per truck (from driver checklists)
        $openIssuesByTruck = \App\Models\DailyChecklistIssue::query()
            ->where('flagged', true)
            ->whereNull('resolved_at')
            ->join('daily_checklists', 'daily_checklist_issues.daily_checklist_id', '=', 'daily_checklists.id')
            ->selectRaw('daily_checklists.truck_id, count(*) as cnt')
            ->groupBy('daily_checklists.truck_id')
            ->pluck('cnt', 'truck_id');

        // Count open inspection findings per truck
        $openInspectionIssuesByTruck = InspectionChecklistIssue::query()
            ->where('flagged', true)
            ->whereNull('resolved_at')
            ->join('inspection_checklists', 'inspection_checklists.id', '=', 'inspection_checklist_issues.inspection_checklist_id')
            ->selectRaw('inspection_checklists.truck_id, count(*) as cnt')
            ->groupBy('inspection_checklists.truck_id')
            ->pluck('cnt', 'truck_id');

        // Pull the actual flagged inspection issues per truck (preview list for the maintenance modal)
        $inspectionIssuesByTruck = InspectionChecklistIssue::query()
            ->where('flagged', true)
            ->whereNull('resolved_at')
            ->join('inspection_checklists', 'inspection_checklists.id', '=', 'inspection_checklist_issues.inspection_checklist_id')
            ->orderByDesc('inspection_checklists.inspection_date')
            ->get([
                'inspection_checklist_issues.id',
                'inspection_checklist_issues.category',
                'inspection_checklist_issues.severity',
                'inspection_checklist_issues.issue_notes',
                'inspection_checklists.truck_id',
                'inspection_checklists.inspection_date',
            ])
            ->groupBy('truck_id');

        $trucks = Truck::with(['maintenanceProfiles' => fn ($q) => $q->active()])
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get()
            ->map(function (Truck $truck) use ($openIssuesByTruck, $openInspectionIssuesByTruck, $inspectionIssuesByTruck) {
                $profiles = $truck->maintenanceProfiles->keyBy('maintenance_type');
                $general = $profiles->get('general');
                return [
                    'id' => $truck->id,
                    'matricule' => $truck->matricule,
                    'total_kilometers' => $truck->total_kilometers,
                    'maintenance_type' => $truck->maintenance_type,
                    'profiles' => $profiles->map(fn ($p) => [
                        'type' => $p->maintenance_type,
                        'interval_km' => $p->interval_km,
                        'next_km' => $p->next_maintenance_km,
                        'remaining' => max(0, $p->next_maintenance_km - $truck->total_kilometers),
                        'status' => $p->status,
                    ])->values(),
                    'overall_status' => $general?->status ?? 'green',
                    'open_issues' => $openIssuesByTruck->get($truck->id, 0),
                    'open_inspection_issues' => $openInspectionIssuesByTruck->get($truck->id, 0),
                    'inspection_issues' => $inspectionIssuesByTruck->get($truck->id, collect())->map(fn ($i) => [
                        'id' => $i->id,
                        'category' => $i->category,
                        'severity' => $i->severity,
                        'issue_notes' => $i->issue_notes,
                        'inspection_date' => $i->inspection_date,
                    ])->values(),
                ];
            });

        $counts = [
            'overdue' => $trucks->where('overall_status', 'red')->count(),
            'warning' => $trucks->where('overall_status', 'yellow')->count(),
            'ok' => $trucks->where('overall_status', 'green')->count(),
        ];

        $maintenanceTypes = collect(config('maintenance.types', []))->map(fn ($cfg, $key) => [
            'value' => $key,
            'label' => $cfg['label'] ?? $key,
        ])->values();

        return Inertia::render('maintenance/Index', [
            'trucks' => $trucks,
            'counts' => $counts,
            'maintenanceTypes' => $maintenanceTypes,
            'oilTypes' => Maintenance::OIL_TYPES,
        ]);
    }

    public function rules()
    {
        $profiles = TruckMaintenanceProfile::with('truck')
            ->orderByDesc('is_active')
            ->orderBy('truck_id')
            ->paginate(20)
            ->through(fn (TruckMaintenanceProfile $p) => [
                'id' => $p->id,
                'truck_id' => $p->truck_id,
                'truck' => $p->truck?->matricule,
                'maintenance_type' => $p->maintenance_type,
                'interval_km' => $p->interval_km,
                'warning_threshold_km' => $p->warning_threshold_km,
                'status' => $p->status,
                'is_active' => $p->is_active,
                'deactivated_at' => $p->deactivated_at?->format('d/m/Y'),
                'created_at' => $p->created_at?->format('d/m/Y'),
            ]);

        $trucks = Truck::where('is_active', true)->orderBy('matricule')->get(['id', 'matricule']);
        $maintenanceTypes = collect(config('maintenance.types', []))->map(fn ($cfg, $key) => [
            'value' => $key,
            'label' => $cfg['label'] ?? $key,
        ])->values();

        return Inertia::render('maintenance/Rules', [
            'profiles' => $profiles,
            'trucks' => $trucks,
            'maintenanceTypes' => $maintenanceTypes,
        ]);
    }

    public function storeRule(Request $request)
    {
        $data = $request->validate([
            'truck_id' => 'required|exists:trucks,id',
            'maintenance_type' => 'required|string',
            'interval_km' => 'required|numeric|min:100',
            'warning_threshold_km' => 'nullable|numeric|min:0',
        ]);

        $truck = Truck::findOrFail($data['truck_id']);

        $this->maintenanceStatusService->createRule(
            $truck,
            $data['maintenance_type'],
            (float) $data['interval_km'],
            isset($data['warning_threshold_km']) ? (float) $data['warning_threshold_km'] : null,
            auth()->id()
        );

        return redirect()->back()->with('success', 'Règle de maintenance créée avec succès.');
    }

    public function deactivateRule(TruckMaintenanceProfile $profile)
    {
        $this->maintenanceStatusService->deactivateRule($profile);
        return redirect()->back()->with('success', 'Règle désactivée avec succès.');
    }

    public function recordMaintenance(Request $request, Truck $truck)
    {
        $oilTypeKeys = implode(',', array_keys(Maintenance::OIL_TYPES));

        $data = $request->validate([
            'maintenance_date' => 'required|date',
            'notes' => 'nullable|string',
            'kilometers_at_maintenance' => 'nullable|numeric',

            'oil_type' => "nullable|string|in:{$oilTypeKeys}",
            'oil_change_km' => 'nullable|numeric|min:0',
            'next_oil_change_km' => 'nullable|numeric|min:0',
            'gearbox_status' => 'nullable|string|max:64',
            'differential_status' => 'nullable|string|max:64',
            'hydraulic_status' => 'nullable|string|max:64',
            'greasing_status' => 'nullable|string|max:64',
            'filter_oil_changed' => 'sometimes|boolean',
            'filter_hydraulic_changed' => 'sometimes|boolean',
            'filter_air_changed' => 'sometimes|boolean',
            'filter_fuel_changed' => 'sometimes|boolean',

            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',

            'linked_inspection_issue_ids' => 'nullable|array',
            'linked_inspection_issue_ids.*' => 'integer|exists:inspection_checklist_issues,id',
        ]);

        try {
            $maintenance = DB::transaction(function () use ($truck, $data, $request) {
                $maintenance = $this->maintenanceStatusService->recordMaintenance(
                    $truck,
                    $data['maintenance_date'],
                    $data['notes'] ?? null,
                    Maintenance::TYPE_GENERAL,
                    isset($data['kilometers_at_maintenance']) ? (float) $data['kilometers_at_maintenance'] : null
                );

                if ($maintenance === false) {
                    return false;
                }

                $extras = array_intersect_key($data, array_flip([
                    'oil_type',
                    'oil_change_km',
                    'next_oil_change_km',
                    'gearbox_status',
                    'differential_status',
                    'hydraulic_status',
                    'greasing_status',
                    'filter_oil_changed',
                    'filter_hydraulic_changed',
                    'filter_air_changed',
                    'filter_fuel_changed',
                ]));

                foreach (['filter_oil_changed', 'filter_hydraulic_changed', 'filter_air_changed', 'filter_fuel_changed'] as $flag) {
                    $extras[$flag] = (bool) ($extras[$flag] ?? false);
                }

                if (!empty($extras)) {
                    $maintenance->update($extras);
                }

                $issueIds = $data['linked_inspection_issue_ids'] ?? [];
                if (!empty($issueIds)) {
                    InspectionChecklistIssue::query()
                        ->whereIn('id', $issueIds)
                        ->whereHas('inspectionChecklist', fn ($q) => $q->where('truck_id', $truck->id))
                        ->whereNull('resolved_at')
                        ->update([
                            'maintenance_id' => $maintenance->id,
                            'resolved_at' => now(),
                            'resolved_by' => auth()->id(),
                            'resolution_notes' => $data['notes'] ?? null,
                        ]);
                }

                return $maintenance;
            });

            if ($maintenance === false) {
                return redirect()->back()->with('error', 'Une maintenance existe déjà pour cette date et ce type.');
            }

            $this->handleMaintenanceAttachment($request, $maintenance);

            LogisticsAlert::where('truck_id', $truck->id)
                ->where('type', 'due_engine')
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);

            return redirect()->back()->with('success', 'Maintenance enregistrée avec succès.');
        } catch (\Throwable $e) {
            Log::error('recordMaintenance failed', ['truck_id' => $truck->id, 'error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function handleMaintenanceAttachment(Request $request, Maintenance $maintenance): void
    {
        if (!$request->hasFile('attachment')) {
            return;
        }

        if (!$this->sharePointStorage->isConfigured()) {
            Log::warning('SharePoint not configured — skipping maintenance attachment upload', [
                'maintenance_id' => $maintenance->id,
            ]);
            return;
        }

        $file = $request->file('attachment');
        $result = $this->sharePointStorage->upload($file, 'maintenances');

        if (!($result['success'] ?? false)) {
            Log::warning('Maintenance attachment upload failed', [
                'maintenance_id' => $maintenance->id,
                'message' => $result['message'] ?? null,
            ]);
            return;
        }

        $maintenance->update([
            'attachment_path' => $result['path'],
            'attachment_url' => $result['url'],
            'attachment_filename' => $file->getClientOriginalName(),
        ]);
    }

    public function history(Request $request)
    {
        $query = Maintenance::with(['truck', 'profile'])
            ->orderByDesc('maintenance_date');

        if ($request->truck_id) {
            $query->where('truck_id', $request->truck_id);
        }
        if ($request->maintenance_type) {
            $query->where('maintenance_type', $request->maintenance_type);
        }

        $maintenances = $query->paginate(20)->through(fn (Maintenance $m) => [
            'id' => $m->id,
            'truck' => $m->truck?->matricule,
            'maintenance_type' => $m->maintenance_type,
            'maintenance_date' => $m->maintenance_date?->format('d/m/Y'),
            'kilometers_at_maintenance' => $m->kilometers_at_maintenance,
            'trigger_km' => $m->trigger_km,
            'interval_km' => $m->profile?->interval_km,
            'notes' => $m->notes,
            'oil_type' => $m->oil_type,
            'oil_type_label' => $m->oil_type ? (Maintenance::OIL_TYPES[$m->oil_type] ?? $m->oil_type) : null,
            'oil_change_km' => $m->oil_change_km,
            'next_oil_change_km' => $m->next_oil_change_km,
            'gearbox_status' => $m->gearbox_status,
            'differential_status' => $m->differential_status,
            'hydraulic_status' => $m->hydraulic_status,
            'greasing_status' => $m->greasing_status,
            'filter_oil_changed' => (bool) $m->filter_oil_changed,
            'filter_hydraulic_changed' => (bool) $m->filter_hydraulic_changed,
            'filter_air_changed' => (bool) $m->filter_air_changed,
            'filter_fuel_changed' => (bool) $m->filter_fuel_changed,
            'attachment_url' => $m->attachment_url,
            'attachment_filename' => $m->attachment_filename,
        ]);

        $trucks = Truck::orderBy('matricule')->get(['id', 'matricule']);
        $maintenanceTypes = collect(config('maintenance.types', []))->map(fn ($cfg, $key) => [
            'value' => $key,
            'label' => $cfg['label'] ?? $key,
        ])->values();

        return Inertia::render('maintenance/History', [
            'maintenances' => $maintenances,
            'trucks' => $trucks,
            'maintenanceTypes' => $maintenanceTypes,
            'filters' => $request->only(['truck_id', 'maintenance_type']),
        ]);
    }
}
