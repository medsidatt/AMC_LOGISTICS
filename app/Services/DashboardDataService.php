<?php

namespace App\Services;

use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\Driver;
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
        $tripsToday = TransportTracking::whereDate('provider_date', today())->count();
        $tripsYesterday = TransportTracking::whereDate('provider_date', today()->subDay())->count();

        $tonnageMonth = TransportTracking::whereMonth('provider_date', now()->month)
            ->whereYear('provider_date', now()->year)
            ->sum('provider_net_weight');

        $tonnageLastMonth = TransportTracking::whereMonth('provider_date', now()->subMonth()->month)
            ->whereYear('provider_date', now()->subMonth()->year)
            ->sum('provider_net_weight');

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
                'provider_date' => $t->provider_date?->format('Y-m-d'),
            ]);

        $monthlyTonnage = TransportTracking::select(
                DB::raw("DATE_FORMAT(provider_date, '%Y-%m') as month"),
                DB::raw('SUM(provider_net_weight) as provider_total'),
                DB::raw('SUM(client_net_weight) as client_total'),
                DB::raw('COUNT(*) as trip_count')
            )
            ->where('provider_date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $months = $monthlyTonnage->pluck('month')
            ->map(fn ($m) => Carbon::createFromFormat('Y-m', $m)->format('M Y'))
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
        $utilization = $activeTrucks->map(function ($truck) {
            $rotations = TransportTracking::where('truck_id', $truck->id)
                ->whereMonth('provider_date', now()->month)
                ->whereYear('provider_date', now()->year)
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
                    'provider_date' => $t->provider_date?->format('Y-m-d'),
                ]);

            $checklistHistory = DailyChecklist::with('issues')
                ->where('driver_id', $driver->id)
                ->orderByDesc('checklist_date')
                ->take(14)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'checklist_date' => $c->checklist_date,
                    'issues_count' => $c->issues->count(),
                    'unresolved_count' => $c->issues->whereNull('resolved_at')->count(),
                ]);
        }

        return [
            'driver' => $driver ? ['id' => $driver->id, 'name' => $driver->name, 'email' => $driver->email] : null,
            'truck' => $truck ? ['id' => $truck->id, 'matricule' => $truck->matricule] : null,
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
                'created_at' => $a->created_at->format('Y-m-d H:i'),
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
                'checklist_date' => $c->checklist_date,
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
}
