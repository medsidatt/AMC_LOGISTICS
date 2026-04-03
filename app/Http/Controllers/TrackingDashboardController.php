<?php

namespace App\Http\Controllers;

use App\Models\TransportTracking; // assume model TransportTracking exists and points to 'trackings' table
use App\Models\Driver;
use App\Models\Transporter;
use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrackingDashboardController extends Controller
{
    // threshold for flagging large gaps (kg)
    protected int $gapThresholdKg = 150; // adjust as needed
    // threshold percent (e.g., 2.5%)
    protected float $gapThresholdPercent = 2.5;

    public function index(Request $req)
    {
        // Filters (with defaults)
        $from = $req->input('from') ? Carbon::parse($req->input('from'))->startOfDay() : now()->subMonths(3)->startOfDay();
        $to   = $req->input('to')   ? Carbon::parse($req->input('to'))->endOfDay()   : now()->endOfDay();

        $driverId   = $req->input('driver_id');
        $truckId    = $req->input('truck_id');
        $providerId = $req->input('provider_id');
        $product    = $req->input('product');
        $base       = $req->input('base');

        // base query with filters
        $q = TransportTracking::query()
            ->whereBetween('provider_date', [$from, $to]);

        if ($driverId) $q->where('driver_id', $driverId);
        if ($truckId)  $q->where('truck_id', $truckId);
        if ($providerId) $q->where('provider_id', $providerId);
        if ($product)  $q->where('product', $product);
        if ($base)     $q->where('base', $base);

        // --- KPIs ---
        // 1) Suspicious drivers count (distinct drivers with >=1 large anomaly in period)
        $suspiciousDrivers = (clone $q)
            ->whereRaw('ABS(provider_net_weight - client_net_weight) > ?', [$this->gapThresholdKg])
            ->distinct('driver_id')
            ->count('driver_id');

        // 2) Total discrepancies count & sum absolute gap
        $totalDiscrepanciesCount = (clone $q)
            ->whereRaw('provider_net_weight IS NOT NULL AND client_net_weight IS NOT NULL')
            ->whereRaw('provider_net_weight <> client_net_weight')
            ->count();

        $totalDiscrepancyKg = (clone $q)
            ->selectRaw('SUM(ABS(provider_net_weight - client_net_weight)) as sum_gap')
            ->value('sum_gap') ?? 0;

        // 3) Monthly tonnage (provider_net_weight sum grouped by month)
        $monthlyTonnageRaw = (clone $q)
            ->selectRaw("DATE_FORMAT(provider_date, '%Y-%m') as ym, SUM(provider_net_weight) as tonnage")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $months = $monthlyTonnageRaw->pluck('ym')->map(function($ym){
            return \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('M Y');
        })->toArray();

        $monthlyTonnage = $monthlyTonnageRaw->pluck('tonnage')->map(fn($v) => round($v / 1000, 2))->toArray(); // convert kg -> T if needed

        // 4) Driver risk score (sum absolute gap + penalty for large anomalies)
        // We compute per driver: sum_abs_gap, large_count, risk_score
        $driverRisk = (clone $q)
            ->selectRaw('driver_id, SUM(ABS(provider_net_weight - client_net_weight)) as sum_gap, SUM(CASE WHEN ABS(provider_net_weight - client_net_weight) > ? THEN 1 ELSE 0 END) as large_count', [$this->gapThresholdKg])
            ->groupBy('driver_id')
            ->orderByDesc('sum_gap')
            ->limit(50)
            ->get();

        $driverRisk = $driverRisk->map(function($r){
            $r->risk_score = ($r->sum_gap) + ($r->large_count * 500); // weight large_count with 500 kg penalty (tunable)
            return $r;
        });

        // 5) Recent anomalies list (trips with gap beyond thresholds)
        $anomalies = (clone $q)
            ->whereRaw('ABS(provider_net_weight - client_net_weight) > ?', [$this->gapThresholdKg])
            ->with(['driver','truck','provider']) // eager load if relations exist
            ->orderByDesc('provider_date')
            ->limit(200)
            ->get();

        // 6) Timeline / latest trackings (for selected driver or truck or global)
        $timeline = (clone $q)
            ->with(['driver','truck'])
            ->orderByDesc('provider_date')
            ->limit(100)
            ->get();

        // 7) Totals (this month, this year)
        $thisMonthTonnage = TransportTracking::whereMonth('provider_date', now()->month)
            ->whereYear('provider_date', now()->year)
            ->sum('provider_net_weight');

        $thisYearTonnage = TransportTracking::whereYear('provider_date', now()->year)
            ->sum('provider_net_weight');

        $totalTonnage = TransportTracking::sum('provider_net_weight');

        // 8) table of trips for view (paginated)
        $trips = (clone $q)
            ->with(['driver','truck','provider'])
            ->orderByDesc('provider_date')
            ->paginate(25)
            ->appends($req->query());

        // Provide supporting lists for filters
        $drivers = Driver::orderBy('name')->get();
        $trucks  = Truck::orderBy('matricule')->get();

        return view('pages.transport_trackings.reports', compact(
            'suspiciousDrivers',
            'totalDiscrepanciesCount',
            'totalDiscrepancyKg',
            'months',
            'monthlyTonnage',
            'driverRisk',
            'anomalies',
            'timeline',
            'thisMonthTonnage',
            'thisYearTonnage',
            'totalTonnage',
            'trips',
            'drivers',
            'trucks'
        ));
    }
}
