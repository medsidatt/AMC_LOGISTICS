<?php

namespace App\Services;

use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\Driver;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistIssue;
use App\Models\LogisticsAlert;
use App\Models\TransportTracking;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardDataService
{
    public function getAdminData(): array
    {
        $trucksCount = Truck::whereNull('deleted_at')->count();
        $driversCount = Driver::whereNull('deleted_at')->count();
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
        $todayChecklist = null;
        $myTripsMonth = 0;
        $myTonnageMonth = 0;
        $recentTrips = collect();
        $checklistHistory = collect();

        if ($driver) {
            $latestTracking = TransportTracking::where('driver_id', $driver->id)
                ->whereNotNull('truck_id')
                ->orderByDesc('client_date')
                ->first();

            if ($latestTracking) {
                $truck = Truck::where('id', $latestTracking->truck_id)->where('is_active', true)->first();
            }

            if ($truck) {
                $todayChecklist = DailyChecklist::where('driver_id', $driver->id)
                    ->where('truck_id', $truck->id)
                    ->whereDate('checklist_date', today())
                    ->first();
            }

            $myTripsMonth = TransportTracking::where('driver_id', $driver->id)
                ->whereMonth('provider_date', now()->month)
                ->whereYear('provider_date', now()->year)
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
            ] : null,
            'todayChecklistDone' => $todayChecklist !== null,
            'myTripsMonth' => $myTripsMonth,
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
        $myInspections = InspectionChecklist::query()
            ->where('inspector_id', $user->id);

        $kpis = [
            'drafts' => (clone $myInspections)->where('status', InspectionChecklist::STATUS_DRAFT)->count(),
            'submitted' => (clone $myInspections)->where('status', InspectionChecklist::STATUS_SUBMITTED)->count(),
            'validated' => (clone $myInspections)->where('status', InspectionChecklist::STATUS_VALIDATED)->count(),
            'rejected' => (clone $myInspections)->where('status', InspectionChecklist::STATUS_REJECTED)->count(),
            'open_critical_issues' => InspectionChecklistIssue::query()
                ->whereHas('inspectionChecklist', fn ($q) => $q->where('inspector_id', $user->id))
                ->where('severity', 'critical')
                ->whereNull('resolved_at')
                ->count(),
        ];

        $recent = (clone $myInspections)
            ->with(['truck:id,matricule', 'issues'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (InspectionChecklist $i) => [
                'id' => $i->id,
                'inspection_date' => $i->inspection_date?->format('d/m/Y'),
                'truck' => $i->truck?->matricule,
                'category' => $i->category,
                'status' => $i->status,
                'issues_count' => $i->issues->count(),
            ])->values();

        $cutoff = now()->subDays(30)->toDateString();
        $trucksNeedingInspection = Truck::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereDoesntHave('inspectionChecklists', function ($q) use ($cutoff) {
                $q->whereDate('inspection_date', '>=', $cutoff);
            })
            ->orderBy('matricule')
            ->limit(10)
            ->get(['id', 'matricule'])
            ->map(fn ($t) => ['id' => $t->id, 'matricule' => $t->matricule]);

        return [
            'kpis' => $kpis,
            'recentInspections' => $recent,
            'trucksNeedingInspection' => $trucksNeedingInspection,
        ];
    }

    public function getLogisticsResponsibleData(): array
    {
        $pendingChecklists = DailyChecklist::query()->where('status', DailyChecklist::STATUS_PENDING)->count();
        $pendingInspections = InspectionChecklist::query()->where('status', InspectionChecklist::STATUS_SUBMITTED)->count();
        $unresolvedFlagged = DailyChecklistIssue::query()->where('flagged', true)->whereNull('resolved_at')->count();
        $unresolvedInspectionFlagged = InspectionChecklistIssue::query()->where('flagged', true)->whereNull('resolved_at')->count();

        $dueEngineTrucks = Truck::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Truck $t) => (float) $t->total_kilometers >= (float) $t->nextMaintenanceAtKm())
            ->count();

        $nextChecklists = DailyChecklist::query()
            ->where('status', DailyChecklist::STATUS_PENDING)
            ->with(['truck:id,matricule', 'driver:id,name', 'issues'])
            ->orderByDesc('week_start_date')
            ->limit(5)
            ->get()
            ->map(fn (DailyChecklist $c) => [
                'id' => $c->id,
                'week_start_date' => $c->week_start_date?->format('d/m/Y'),
                'truck' => $c->truck?->matricule,
                'driver' => $c->driver?->name,
                'issues_count' => $c->issues->count(),
            ])->values();

        $nextInspections = InspectionChecklist::query()
            ->where('status', InspectionChecklist::STATUS_SUBMITTED)
            ->with(['truck:id,matricule', 'inspector:id,name', 'issues'])
            ->orderByDesc('inspection_date')
            ->limit(5)
            ->get()
            ->map(fn (InspectionChecklist $i) => [
                'id' => $i->id,
                'inspection_date' => $i->inspection_date?->format('d/m/Y'),
                'truck' => $i->truck?->matricule,
                'inspector' => $i->inspector?->name,
                'category' => $i->category,
                'critical_count' => $i->issues->where('severity', 'critical')->count(),
            ])->values();

        $alerts = LogisticsAlert::query()
            ->whereNull('read_at')
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'message' => $a->message,
                'created_at' => $a->created_at->format('d/m/Y H:i'),
            ]);

        return [
            'kpis' => [
                'pending_checklists' => $pendingChecklists,
                'pending_inspections' => $pendingInspections,
                'unresolved_flagged' => $unresolvedFlagged,
                'unresolved_inspection_flagged' => $unresolvedInspectionFlagged,
                'due_engine_trucks' => $dueEngineTrucks,
            ],
            'nextChecklists' => $nextChecklists,
            'nextInspections' => $nextInspections,
            'alerts' => $alerts,
        ];
    }
}
