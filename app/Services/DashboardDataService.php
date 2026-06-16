<?php

namespace App\Services;

use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\Driver;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistIssue;
use App\Models\LogisticsAlert;
use App\Models\Maintenance;
use App\Models\TheftIncident;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Services\FleetCapacityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardDataService
{
    public function __construct(
        private ?FleetCapacityService $capacityService = null,
    ) {}

    private function capacity(): FleetCapacityService
    {
        return $this->capacityService ??= app(FleetCapacityService::class);
    }

    public function getAdminData(): array
    {
        $trucksCount = Truck::where('is_active', true)->count();
        $driversCount = Driver::where('is_active', true)->count();
        $tripsToday = TransportTracking::whereDate('client_date', today())->count();
        $tripsYesterday = TransportTracking::whereDate('client_date', today()->subDay())->count();

        // Month = 22nd of previous month to 21st of current month
        $currentMonthStart = now()->day >= 22
            ? now()->copy()->setDay(22)->startOfDay()
            : now()->copy()->subMonth()->setDay(22)->startOfDay();
        $currentMonthEnd = now()->day >= 22
            ? now()->copy()->addMonth()->setDay(21)->endOfDay()
            : now()->copy()->setDay(21)->endOfDay();

        $lastMonthStart = $currentMonthStart->copy()->subMonth();
        $lastMonthEnd = $currentMonthStart->copy()->subDay()->endOfDay();

        $tonnageMonth = TransportTracking::whereBetween('client_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('client_net_weight');

        $tonnageLastMonth = TransportTracking::whereBetween('client_date', [$lastMonthStart, $lastMonthEnd])
            ->sum('client_net_weight');

        $unresolvedAlerts = LogisticsAlert::whereNull('deleted_at')->count();

        $recentTrackings = TransportTracking::with(['truck', 'driver', 'provider'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'truck' => $t->truck?->matricule,
                'driver' => $t->driver?->name,
                'provider' => $t->provider?->name,
                'provider_net_weight' => $t->provider_net_weight,
                'client_net_weight' => $t->client_net_weight,
                'gap' => $t->gap,
                'client_date' => $t->client_date?->format('d/m/Y'),
            ]);

        // Monthly tonnage grouped by custom periods (22nd to 21st), using client_date
        // Day >= 22 belongs to next month's period, Day 1-21 belongs to current month's period
        $monthlyTonnage = TransportTracking::select(
                DB::raw("CASE WHEN DAY(client_date) >= 22 THEN DATE_FORMAT(DATE_ADD(client_date, INTERVAL 1 MONTH), '%Y-%m') ELSE DATE_FORMAT(client_date, '%Y-%m') END as month"),
                DB::raw('SUM(provider_net_weight) as provider_total'),
                DB::raw('SUM(client_net_weight) as client_total'),
                DB::raw('COUNT(*) as trip_count')
            )
            ->whereNotNull('client_date')
            ->where('client_date', '>=', now()->subMonths(6)->setDay(22)->startOfDay())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $months = $monthlyTonnage->pluck('month')
            ->map(fn ($m) => Carbon::createFromFormat('Y-m', $m)->translatedFormat('M Y'))
            ->toArray();

        $trucksDueMaintenance = Truck::whereNull('deleted_at')
            ->where('is_active', true)
            ->get()
            ->filter(fn ($truck) => $truck->maintenanceLevelByType() === 'red')
            ->take(5)
            ->values()
            ->map(fn ($t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'maintenance_type' => $t->maintenance_type,
                'total_kilometers' => $t->total_kilometers,
            ]);

        // Vehicle utilization
        $activeTrucks = Truck::whereNull('deleted_at')->where('is_active', true)->get();
        $utilization = $activeTrucks->map(function ($truck) use ($currentMonthStart, $currentMonthEnd) {
            $rotations = TransportTracking::where('truck_id', $truck->id)
                ->whereBetween('client_date', [$currentMonthStart, $currentMonthEnd])
                ->count();
            $maxRotations = config('logistics.max_rotations_before_maintenance', 12);
            return [
                'label' => $truck->matricule,
                'value' => min(100, round(($rotations / max(1, $maxRotations)) * 100)),
            ];
        })->take(6)->values();

        return [
            'trucksCount' => $trucksCount,
            'driversCount' => $driversCount,
            'tripsToday' => $tripsToday,
            'tripsYesterday' => $tripsYesterday,
            'tonnageMonth' => round($tonnageMonth, 2),
            'tonnageLastMonth' => round($tonnageLastMonth, 2),
            'unresolvedAlerts' => $unresolvedAlerts,
            'recentTrackings' => $recentTrackings,
            'months' => $months,
            'monthlyProvider' => $monthlyTonnage->pluck('provider_total')->map(fn ($v) => round($v ?? 0, 2))->toArray(),
            'monthlyClient' => $monthlyTonnage->pluck('client_total')->map(fn ($v) => round($v ?? 0, 2))->toArray(),
            'monthlyTrips' => $monthlyTonnage->pluck('trip_count')->toArray(),
            'trucksDueMaintenance' => $trucksDueMaintenance,
            'utilization' => $utilization,
            'fleetCapacity' => $this->buildFleetCapacitySnapshot(),
        ];
    }

    /**
     * Aggregated fleet capacity snapshot for the dashboard.
     * Business target: 3 rotations × 45 t = 135 t per truck per week.
     */
    private function buildFleetCapacitySnapshot(): array
    {
        $service = $this->capacity();
        $activeTrucks = Truck::query()->where('is_active', true)->orderBy('matricule')->get();

        // Cascade resolver : per-truck > client demand > global default
        $aggregate = $service->resolveWeeklyTarget(Carbon::now());

        $perTruck = $activeTrucks
            ->map(function (Truck $t) use ($service) {
                $info = $service->truckDailyCapacity($t);
                return [
                    'truck_id' => $t->id,
                    'matricule' => $t->matricule,
                    'capacity_tonnage' => $info['capacity_tonnage'],
                    'avg_rotations_per_week' => $info['avg_rotations_per_week'],
                    'target_weekly_capacity_t' => $info['target_weekly_capacity_t'],
                    'target_rotations_per_week' => $info['target_rotations_per_week'],
                    'target_is_custom' => $info['target_is_custom'],
                    'empirical_weekly_capacity_t' => $info['empirical_weekly_capacity_t'],
                    'this_week_rotations' => $info['this_week_rotations'],
                    'this_week_tonnage_t' => $info['this_week_tonnage_t'],
                    'target_rate' => $info['target_weekly_capacity_t'] > 0
                        ? round(min(1, $info['this_week_tonnage_t'] / $info['target_weekly_capacity_t']), 4)
                        : 0.0,
                ];
            });

        $totalDeliveredThisWeek = round($perTruck->sum('this_week_tonnage_t'), 2);
        $totalThisWeekRotations = (int) $perTruck->sum('this_week_rotations');

        // Use cascade target for utilization
        $targetTons = (float) $aggregate['target_tons'];
        $targetRotations = $aggregate['target_rotations'] ?? (int) $perTruck->sum('target_rotations_per_week');

        $utilizationPct = $targetTons > 0
            ? round(min(100, ($totalDeliveredThisWeek / $targetTons) * 100), 1)
            : 0.0;

        $top = $perTruck->sortByDesc('this_week_tonnage_t')->take(5)->values()->all();
        $bottom = $perTruck->sortBy('this_week_tonnage_t')->take(5)->values()->all();

        return [
            'active_trucks' => $activeTrucks->count(),
            'target_source' => $aggregate['source'],
            'target_rotations_per_truck_per_week' => $service->defaultTargetRotationsPerWeek(),
            'default_capacity_tonnage' => $service->defaultCapacityTonnage(),
            'avg_capacity_t' => $aggregate['avg_capacity_t'] ?? $service->defaultCapacityTonnage(),
            'custom_truck_count' => $aggregate['custom_truck_count'] ?? 0,
            'target_weekly_capacity_t' => $targetTons,
            'delivered_this_week_t' => $totalDeliveredThisWeek,
            'rotations_this_week' => $totalThisWeekRotations,
            'target_rotations_this_week' => $targetRotations,
            'utilization_pct' => $utilizationPct,
            'top_trucks' => $top,
            'bottom_trucks' => $bottom,
        ];
    }

    public function getDriverData($user): array
    {
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            $driver = Driver::where('email', $user->email)
                ->orWhere('name', $user->name)
                ->first();
        }

        $truck = null;
        $weekChecklistDone = false;
        $openIssuesCount = 0;
        $myTripsMonth = 0;
        $myTripsWeek = 0;
        $myTonnageMonth = 0;
        $recentTrips = collect();
        $checklistHistory = collect();

        if ($driver) {
            // A driver's truck is the explicit assignment only (indexed FK).
            // Unassigned drivers have no truck — no scan over trackings.
            if ($driver->current_truck_id) {
                $truck = Truck::where('id', $driver->current_truck_id)->where('is_active', true)->first();
            }

            if ($truck) {
                // Weekly checklist — "is this week done?" via the week_start_date column
                $weekChecklistDone = DailyChecklist::where('driver_id', $driver->id)
                    ->where('truck_id', $truck->id)
                    ->whereDate('week_start_date', DailyChecklist::weekStartFor(now())->toDateString())
                    ->exists();
            }

            $openIssuesCount = DailyChecklistIssue::query()
                ->whereHas('dailyChecklist', fn ($q) => $q->where('driver_id', $driver->id))
                ->whereNull('resolved_at')
                ->where('flagged', true)
                ->count();

            $myTripsMonth = TransportTracking::where('driver_id', $driver->id)
                ->whereMonth('provider_date', now()->month)
                ->whereYear('provider_date', now()->year)
                ->count();

            $myTripsWeek = TransportTracking::where('driver_id', $driver->id)
                ->whereDate('provider_date', '>=', now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString())
                ->count();

            $myTonnageMonth = TransportTracking::where('driver_id', $driver->id)
                ->whereMonth('provider_date', now()->month)
                ->whereYear('provider_date', now()->year)
                ->sum('provider_net_weight');

            $recentTrips = TransportTracking::with(['truck', 'provider'])
                ->where('driver_id', $driver->id)
                ->latest('provider_date')
                ->take(10)
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'reference' => $t->reference,
                    'truck' => $t->truck?->matricule,
                    'provider' => $t->provider?->name,
                    'provider_net_weight' => $t->provider_net_weight,
                    'client_net_weight' => $t->client_net_weight,
                    'provider_date' => $t->provider_date?->format('d/m/Y'),
                    'client_date' => $t->client_date?->format('d/m/Y'),
                ]);

            $checklistHistory = DailyChecklist::with('issues')
                ->where('driver_id', $driver->id)
                ->orderByDesc('checklist_date')
                ->take(14)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'checklist_date' => $c->checklist_date instanceof \Carbon\Carbon ? $c->checklist_date->format('d/m/Y') : $c->checklist_date,
                    'issues_count' => $c->issues->count(),
                    'unresolved_count' => $c->issues->whereNull('resolved_at')->count(),
                ]);
        }

        return [
            'driver' => $driver ? ['id' => $driver->id, 'name' => $driver->name, 'email' => $driver->email] : null,
            'truck' => $truck ? [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
                'total_kilometers' => (float) ($truck->total_kilometers ?? 0),
                'fuel_level' => $truck->fleeti_last_fuel_level !== null ? (float) $truck->fleeti_last_fuel_level : null,
                'speed' => $truck->fleeti_last_speed_kmh !== null ? (float) $truck->fleeti_last_speed_kmh : null,
                'movement_status' => $truck->fleeti_last_movement_status,
                'last_sync' => $truck->fleeti_last_synced_at?->format('d/m/Y H:i'),
                'maintenance_level' => $truck->maintenanceLevelByType(),
            ] : null,
            'weekChecklistDone' => $weekChecklistDone,
            'openIssuesCount' => $openIssuesCount,
            'myTripsMonth' => $myTripsMonth,
            'myTripsWeek' => $myTripsWeek,
            'myTonnageMonth' => round($myTonnageMonth, 2),
            'recentTrips' => $recentTrips,
            'checklistHistory' => $checklistHistory,
        ];
    }

    public function getLogisticsData(): array
    {
        $alerts = LogisticsAlert::latest()
            ->take(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'message' => $a->message,
                'created_at' => $a->created_at->format('d/m/Y H:i'),
                'resolved_at' => $a->resolved_at,
            ]);

        $dueEngineTrucks = Truck::whereNull('deleted_at')
            ->where('is_active', true)
            ->get()
            ->filter(fn ($t) => $t->maintenanceLevelByType() !== 'green')
            ->map(fn ($t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'maintenance_type' => $t->maintenance_type,
                'level' => $t->maintenanceLevelByType(),
                'total_kilometers' => $t->total_kilometers,
            ])
            ->values();

        $unresolvedIssues = DailyChecklistIssue::whereNull('resolved_at')
            ->with(['dailyChecklist.truck', 'dailyChecklist.driver'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'description' => $i->description,
                'checklist_date' => $i->dailyChecklist?->checklist_date,
                'truck' => $i->dailyChecklist?->truck?->matricule,
                'driver' => $i->dailyChecklist?->driver?->name,
            ]);

        $lastChecklists = DailyChecklist::with(['truck', 'driver', 'issues'])
            ->latest('checklist_date')
            ->take(20)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'checklist_date' => $c->checklist_date instanceof \Carbon\Carbon ? $c->checklist_date->format('d/m/Y') : $c->checklist_date,
                'truck' => $c->truck?->matricule,
                'driver' => $c->driver?->name,
                'issues_count' => $c->issues->count(),
            ]);

        return [
            'alerts' => $alerts,
            'dueEngineTrucks' => $dueEngineTrucks,
            'unresolvedIssues' => $unresolvedIssues,
            'lastChecklists' => $lastChecklists,
        ];
    }

    public function getHseData($user): array
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $monthCutoff = now()->subDays(30);
        $inspectionCutoff = now()->subDays(30)->toDateString();

        // ---------- KPI ROW (ISO 9001 / 45001 audit-relevant) ----------
        $inspectionsThisWeek = InspectionChecklist::query()
            ->where('inspection_date', '>=', $weekStart->toDateString())
            ->count();

        $inspectionsThisMonth = InspectionChecklist::query()
            ->where('inspection_date', '>=', $monthCutoff->toDateString())
            ->count();

        $activeTruckIds = Truck::query()
            ->where('is_active', true)
            ->pluck('id');

        $trucksWithRecentInspectionIds = InspectionChecklist::query()
            ->whereDate('inspection_date', '>=', $inspectionCutoff)
            ->pluck('truck_id')
            ->unique();

        $trucksOverdueInspectionCount = $activeTruckIds
            ->diff($trucksWithRecentInspectionIds)
            ->count();

        $activeTrucks = Truck::query()->where('is_active', true)->get();
        $maintenanceOverdueCount = $activeTrucks->filter(fn (Truck $t) => $t->isMaintenanceDueByType())->count();

        $securityIncidentsOpen = TheftIncident::query()
            ->where('status', TheftIncident::STATUS_PENDING)
            ->count();

        $kpis = [
            'inspections_this_week' => $inspectionsThisWeek,
            'inspections_this_month' => $inspectionsThisMonth,
            'trucks_overdue_inspection' => $trucksOverdueInspectionCount,
            'maintenance_overdue' => $maintenanceOverdueCount,
            'security_incidents_open' => $securityIncidentsOpen,
            'active_trucks' => $activeTruckIds->count(),
        ];

        // ---------- Recent inspections (whole fleet, not just user's) ----------
        $recent = InspectionChecklist::query()
            ->with(['truck:id,matricule', 'inspector:id,name', 'driver:id,name', 'project:id,name'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (InspectionChecklist $i) => [
                'id' => $i->id,
                'inspection_date' => $i->inspection_date?->format('d/m/Y'),
                'truck' => $i->truck?->matricule,
                'inspector' => $i->inspector?->name,
                'driver' => $i->driver?->name,
                'project' => $i->project?->name,
                'vehicle_photo_url' => $i->vehicle_photo_path
                    ? Storage::disk('public')->url($i->vehicle_photo_path)
                    : null,
            ])->values();

        // ---------- Trucks needing inspection (>30 days since last) ----------
        $trucksNeedingInspection = Truck::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereDoesntHave('inspectionChecklists', function ($q) use ($inspectionCutoff) {
                $q->whereDate('inspection_date', '>=', $inspectionCutoff);
            })
            ->orderBy('matricule')
            ->limit(10)
            ->get(['id', 'matricule'])
            ->map(fn ($t) => ['id' => $t->id, 'matricule' => $t->matricule])
            ->values();

        // ---------- Maintenance overdue trucks ----------
        $maintenanceOverdue = $activeTrucks
            ->filter(fn (Truck $t) => $t->isMaintenanceDueByType())
            ->take(10)
            ->map(fn (Truck $t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'counter' => $t->maintenanceCounterByType(),
                'unit' => $t->maintenanceUnitByType(),
                'level' => $t->maintenanceLevelByType(),
            ])
            ->values();

        // ---------- Recent security incidents (pending or confirmed) ----------
        $securityIncidents = TheftIncident::query()
            ->with(['truck:id,matricule'])
            ->whereIn('status', [TheftIncident::STATUS_PENDING, TheftIncident::STATUS_CONFIRMED])
            ->orderByDesc('detected_at')
            ->limit(8)
            ->get()
            ->map(fn (TheftIncident $inc) => [
                'id' => $inc->id,
                'truck' => $inc->truck?->matricule,
                'type' => $inc->type,
                'severity' => $inc->severity,
                'status' => $inc->status,
                'detected_at' => $inc->detected_at?->format('d/m/Y H:i'),
            ])
            ->values();

        return [
            'kpis' => $kpis,
            'recentInspections' => $recent,
            'trucksNeedingInspection' => $trucksNeedingInspection,
            'maintenanceOverdue' => $maintenanceOverdue,
            'securityIncidents' => $securityIncidents,
        ];
    }

    public function getLogisticsResponsibleData($user = null): array
    {
        $weekStart = now()->startOfWeek(\Carbon\Carbon::MONDAY);
        $monthCutoff = now()->subDays(30);
        $inspectionCutoff = now()->subDays(30)->toDateString();

        // ---------- KPI: Activité ----------
        $myInspectionsWeek = InspectionChecklist::query()
            ->when($user, fn ($q) => $q->where('inspector_id', $user->id))
            ->where('inspection_date', '>=', $weekStart->toDateString())
            ->count();

        $inspectionsThisMonth = InspectionChecklist::query()
            ->where('inspection_date', '>=', $monthCutoff->toDateString())
            ->count();

        $tripsToday = TransportTracking::query()
            ->whereDate('client_date', today())
            ->count();

        $activeTrucks = Truck::query()->where('is_active', true)->count();

        // ---------- KPI: Alertes ----------
        $pendingChecklists = DailyChecklist::query()
            ->where('status', DailyChecklist::STATUS_PENDING)
            ->count();

        $unresolvedFlagged = DailyChecklistIssue::query()
            ->where('flagged', true)
            ->whereNull('resolved_at')
            ->count();

        $driverFlaggedIssues = DailyChecklistIssue::query()
            ->where('flagged', true)
            ->whereNull('resolved_at')
            ->with(['truck:id,matricule', 'driver:id,name', 'dailyChecklist.truck:id,matricule', 'dailyChecklist.driver:id,name'])
            ->orderByDesc('reported_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (DailyChecklistIssue $i) => [
                'id' => $i->id,
                'category' => $i->category,
                'severity' => $i->severity,
                'notes' => $i->issue_notes,
                'positions' => $i->positions,
                'truck' => $i->truck?->matricule ?? $i->dailyChecklist?->truck?->matricule,
                'driver' => $i->driver?->name ?? $i->dailyChecklist?->driver?->name,
                'reported_at' => $i->reported_at?->format('d/m/Y H:i'),
            ])
            ->values();

        $activeTruckIds = Truck::query()->where('is_active', true)->pluck('id');
        $trucksWithRecentInspectionIds = InspectionChecklist::query()
            ->whereDate('inspection_date', '>=', $inspectionCutoff)
            ->pluck('truck_id')
            ->unique();
        $trucksOverdueInspection = $activeTruckIds->diff($trucksWithRecentInspectionIds)->count();

        $activeTrucksCollection = Truck::query()->where('is_active', true)->get();
        $maintenanceOverdueCount = $activeTrucksCollection->filter(fn (Truck $t) => $t->isMaintenanceDueByType())->count();

        // ---------- Lists ----------
        $nextChecklists = DailyChecklist::query()
            ->where('status', DailyChecklist::STATUS_PENDING)
            ->with(['truck:id,matricule', 'driver:id,name', 'issues'])
            ->orderByDesc('week_start_date')
            ->limit(8)
            ->get()
            ->map(fn (DailyChecklist $c) => [
                'id' => $c->id,
                'week_start_date' => $c->week_start_date?->format('d/m/Y'),
                'truck' => $c->truck?->matricule,
                'driver' => $c->driver?->name,
                'issues_count' => $c->issues->count(),
            ])->values();

        $recentInspections = InspectionChecklist::query()
            ->with(['truck:id,matricule', 'inspector:id,name', 'driver:id,name'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (InspectionChecklist $i) => [
                'id' => $i->id,
                'inspection_date' => $i->inspection_date?->format('d/m/Y'),
                'truck' => $i->truck?->matricule,
                'inspector' => $i->inspector?->name,
                'driver' => $i->driver?->name,
            ])->values();

        $trucksNeedingInspection = Truck::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereDoesntHave('inspectionChecklists', function ($q) use ($inspectionCutoff) {
                $q->whereDate('inspection_date', '>=', $inspectionCutoff);
            })
            ->orderBy('matricule')
            ->limit(10)
            ->get(['id', 'matricule'])
            ->map(fn ($t) => ['id' => $t->id, 'matricule' => $t->matricule])
            ->values();

        $maintenanceOverdue = $activeTrucksCollection
            ->filter(fn (Truck $t) => $t->isMaintenanceDueByType())
            ->take(10)
            ->map(fn (Truck $t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'counter' => $t->maintenanceCounterByType(),
                'unit' => $t->maintenanceUnitByType(),
                'level' => $t->maintenanceLevelByType(),
            ])
            ->values();

        $alerts = LogisticsAlert::query()
            ->whereNull('read_at')
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'message' => $a->message,
                'created_at' => $a->created_at->format('d/m/Y H:i'),
            ])
            ->values();

        return [
            'kpis' => [
                'my_inspections_week' => $myInspectionsWeek,
                'inspections_this_month' => $inspectionsThisMonth,
                'trips_today' => $tripsToday,
                'active_trucks' => $activeTrucks,
                'pending_checklists' => $pendingChecklists,
                'unresolved_flagged' => $unresolvedFlagged,
                'trucks_overdue_inspection' => $trucksOverdueInspection,
                'maintenance_overdue' => $maintenanceOverdueCount,
            ],
            'pendingChecklists' => $nextChecklists,
            'driverFlaggedIssues' => $driverFlaggedIssues,
            'recentInspections' => $recentInspections,
            'trucksNeedingInspection' => $trucksNeedingInspection,
            'maintenanceOverdue' => $maintenanceOverdue,
            'alerts' => $alerts,
        ];
    }
}
