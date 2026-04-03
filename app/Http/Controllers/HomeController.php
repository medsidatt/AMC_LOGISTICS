<?php

namespace App\Http\Controllers;

use App\Models\DailyChecklist;
use App\Models\Driver;
use App\Models\LogisticsAlert;
use App\Models\TransportTracking;
use App\Models\Truck;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = auth()->user();

        // Driver-specific dashboard
        if ($user->hasRole('Driver')) {
            return $this->driverDashboard($user);
        }

        return $this->adminDashboard();
    }

    private function driverDashboard($user)
    {
        // Find linked driver
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            // Fallback: try email/name match
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
            // Find assigned truck
            $latestTracking = TransportTracking::where('driver_id', $driver->id)
                ->whereNotNull('truck_id')
                ->orderByDesc('client_date')
                ->first();

            if ($latestTracking) {
                $truck = Truck::where('id', $latestTracking->truck_id)->where('is_active', true)->first();
            }

            // Today's checklist status
            if ($truck) {
                $todayChecklist = DailyChecklist::where('driver_id', $driver->id)
                    ->where('truck_id', $truck->id)
                    ->whereDate('checklist_date', today())
                    ->first();
            }

            // My stats this month
            $myTripsMonth = TransportTracking::where('driver_id', $driver->id)
                ->whereMonth('provider_date', now()->month)
                ->whereYear('provider_date', now()->year)
                ->count();

            $myTonnageMonth = TransportTracking::where('driver_id', $driver->id)
                ->whereMonth('provider_date', now()->month)
                ->whereYear('provider_date', now()->year)
                ->sum('provider_net_weight');

            // Recent trips
            $recentTrips = TransportTracking::with(['truck', 'provider'])
                ->where('driver_id', $driver->id)
                ->latest('provider_date')
                ->take(10)
                ->get();

            // Checklist history (last 14 days)
            $checklistHistory = DailyChecklist::with('issues')
                ->where('driver_id', $driver->id)
                ->orderByDesc('checklist_date')
                ->take(14)
                ->get();
        }

        return view('home-driver', [
            'driver' => $driver,
            'truck' => $truck,
            'todayChecklist' => $todayChecklist,
            'myTripsMonth' => $myTripsMonth,
            'myTonnageMonth' => $myTonnageMonth,
            'recentTrips' => $recentTrips,
            'checklistHistory' => $checklistHistory,
        ]);
    }

    private function adminDashboard()
    {
        $trucksCount = Truck::whereNull('deleted_at')->count();
        $driversCount = Driver::whereNull('deleted_at')->count();
        $tripsToday = TransportTracking::whereDate('provider_date', today())->count();
        $tonnageMonth = TransportTracking::whereMonth('provider_date', now()->month)
            ->whereYear('provider_date', now()->year)
            ->sum('provider_net_weight');
        $unresolvedAlerts = LogisticsAlert::whereNull('deleted_at')->count();

        $recentTrackings = TransportTracking::with(['truck', 'driver', 'provider'])
            ->latest()
            ->take(10)
            ->get();

        $monthlyTonnage = TransportTracking::select(
                DB::raw("DATE_FORMAT(provider_date, '%Y-%m') as month"),
                DB::raw('SUM(provider_net_weight) as total_weight'),
                DB::raw('COUNT(*) as trip_count')
            )
            ->where('provider_date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $trucksDueMaintenance = Truck::whereNull('deleted_at')
            ->where('is_active', true)
            ->get()
            ->filter(fn($truck) => $truck->maintenanceLevelByType() === 'red')
            ->take(5)
            ->values();

        return view('home', [
            'trucksCount' => $trucksCount,
            'driversCount' => $driversCount,
            'tripsToday' => $tripsToday,
            'tonnageMonth' => $tonnageMonth,
            'unresolvedAlerts' => $unresolvedAlerts,
            'recentTrackings' => $recentTrackings,
            'monthlyTonnage' => $monthlyTonnage,
            'trucksDueMaintenance' => $trucksDueMaintenance,
        ]);
    }
}
