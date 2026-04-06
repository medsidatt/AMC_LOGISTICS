<?php

namespace App\Http\Controllers;

use App\Models\TransportTracking;
use App\Models\Driver;
use App\Models\Provider;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrackingDashboardController extends Controller
{
    protected int $gapThresholdKg = 150;

    public function index(Request $req)
    {
        $from = $req->input('from') ? Carbon::parse($req->input('from'))->startOfDay() : now()->subMonths(3)->startOfDay();
        $to   = $req->input('to')   ? Carbon::parse($req->input('to'))->endOfDay()     : now()->endOfDay();

        $driverId   = $req->input('driver_id');
        $truckId    = $req->input('truck_id');
        $providerId = $req->input('provider_id');
        $product    = $req->input('product');
        $base       = $req->input('base');

        $q = TransportTracking::query()
            ->whereBetween('provider_date', [$from, $to]);

        if ($driverId)   $q->where('driver_id', $driverId);
        if ($truckId)    $q->where('truck_id', $truckId);
        if ($providerId) $q->where('provider_id', $providerId);
        if ($product)    $q->where('product', $product);
        if ($base)       $q->where('base', $base);

        // --- KPIs ---
        $totalTrips = (clone $q)->count();

        $totalProviderWeight = (clone $q)->sum('provider_net_weight');
        $totalClientWeight   = (clone $q)->sum('client_net_weight');
        $totalGap            = $totalClientWeight - $totalProviderWeight;

        $totalDiscrepanciesCount = (clone $q)
            ->whereNotNull('provider_net_weight')
            ->whereNotNull('client_net_weight')
            ->whereRaw('provider_net_weight <> client_net_weight')
            ->count();

        $totalDiscrepancyKg = (clone $q)
            ->selectRaw('SUM(ABS(provider_net_weight - client_net_weight)) as sum_gap')
            ->value('sum_gap') ?? 0;

        $suspiciousDrivers = (clone $q)
            ->whereRaw('ABS(provider_net_weight - client_net_weight) > ?', [$this->gapThresholdKg])
            ->distinct('driver_id')
            ->count('driver_id');

        $thisMonthTonnage = TransportTracking::whereMonth('provider_date', now()->month)
            ->whereYear('provider_date', now()->year)
            ->sum('provider_net_weight');

        $thisYearTonnage = TransportTracking::whereYear('provider_date', now()->year)
            ->sum('provider_net_weight');

        // --- Charts data ---

        // Monthly tonnage (provider vs client)
        $monthlyRaw = (clone $q)
            ->selectRaw("DATE_FORMAT(provider_date, '%Y-%m') as ym, SUM(provider_net_weight) as prov, SUM(client_net_weight) as client, SUM(ABS(provider_net_weight - client_net_weight)) as gap_sum, COUNT(*) as trips")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $months           = $monthlyRaw->pluck('ym')->map(fn($ym) => Carbon::createFromFormat('Y-m', $ym)->format('M Y'))->toArray();
        $monthlyProvider  = $monthlyRaw->pluck('prov')->map(fn($v) => round($v, 2))->toArray();
        $monthlyClient    = $monthlyRaw->pluck('client')->map(fn($v) => round($v, 2))->toArray();
        $monthlyGap       = $monthlyRaw->pluck('gap_sum')->map(fn($v) => round($v, 2))->toArray();
        $monthlyTrips     = $monthlyRaw->pluck('trips')->toArray();

        // Gap distribution by product
        $gapByProduct = (clone $q)
            ->selectRaw("product, SUM(ABS(provider_net_weight - client_net_weight)) as gap_sum, COUNT(*) as trips")
            ->whereNotNull('product')
            ->groupBy('product')
            ->get();

        // Driver risk ranking (top 10)
        $driverRisk = (clone $q)
            ->selectRaw('driver_id, SUM(ABS(provider_net_weight - client_net_weight)) as sum_gap, COUNT(*) as trip_count, SUM(CASE WHEN ABS(provider_net_weight - client_net_weight) > ? THEN 1 ELSE 0 END) as large_count', [$this->gapThresholdKg])
            ->groupBy('driver_id')
            ->orderByDesc('sum_gap')
            ->limit(10)
            ->get()
            ->map(function ($r) {
                $r->driver = Driver::withTrashed()->find($r->driver_id);
                $r->avg_gap = $r->trip_count > 0 ? round($r->sum_gap / $r->trip_count, 2) : 0;
                return $r;
            });

        // Gap by base
        $gapByBase = (clone $q)
            ->selectRaw("base, SUM(provider_net_weight) as prov, SUM(client_net_weight) as client, SUM(ABS(provider_net_weight - client_net_weight)) as gap_sum, COUNT(*) as trips")
            ->whereNotNull('base')
            ->groupBy('base')
            ->get();

        // Anomalies
        $anomalies = (clone $q)
            ->whereRaw('ABS(provider_net_weight - client_net_weight) > ?', [$this->gapThresholdKg])
            ->with(['driver', 'truck', 'provider'])
            ->orderByDesc('provider_date')
            ->limit(50)
            ->get();

        // Trips paginated
        $trips = (clone $q)
            ->with(['driver', 'truck', 'provider'])
            ->orderByDesc('provider_date')
            ->paginate(25)
            ->appends($req->query());

        // Filter lists
        $drivers   = Driver::orderBy('name')->get();
        $trucks    = Truck::orderBy('matricule')->get();
        $providers = Provider::orderBy('name')->get();

        return \Inertia\Inertia::render('transport-trackings/Reports', [
            'totalTrips' => $totalTrips,
            'totalProviderWeight' => $totalProviderWeight,
            'totalClientWeight' => $totalClientWeight,
            'totalGap' => $totalGap,
            'totalDiscrepanciesCount' => $totalDiscrepanciesCount,
            'totalDiscrepancyKg' => $totalDiscrepancyKg,
            'suspiciousDrivers' => $suspiciousDrivers,
            'thisMonthTonnage' => $thisMonthTonnage,
            'thisYearTonnage' => $thisYearTonnage,
            'months' => $months,
            'monthlyProvider' => $monthlyProvider,
            'monthlyClient' => $monthlyClient,
            'monthlyGap' => $monthlyGap,
            'monthlyTrips' => $monthlyTrips,
            'gapByProduct' => $gapByProduct->map(fn ($g) => [
                'product' => $g->product,
                'gap_sum' => $g->gap_sum,
                'trips' => $g->trips,
            ])->toArray(),
            'gapByBase' => $gapByBase->map(fn ($g) => [
                'base' => $g->base,
                'prov' => $g->prov,
                'client' => $g->client,
                'gap_sum' => $g->gap_sum,
                'trips' => $g->trips,
            ])->toArray(),
            'driverRisk' => $driverRisk->map(fn ($r) => [
                'driver_id' => $r->driver_id,
                'driver_name' => $r->driver?->name ?? 'N/A',
                'sum_gap' => $r->sum_gap,
                'trip_count' => $r->trip_count,
                'large_count' => $r->large_count,
                'avg_gap' => $r->avg_gap,
            ])->toArray(),
            'anomalies' => $anomalies->map(fn ($a) => [
                'id' => $a->id,
                'reference' => $a->reference,
                'provider_date' => $a->provider_date,
                'provider_net_weight' => $a->provider_net_weight,
                'client_net_weight' => $a->client_net_weight,
                'gap' => $a->gap,
                'driver' => $a->driver ? ['id' => $a->driver->id, 'name' => $a->driver->name] : null,
                'truck' => $a->truck ? ['id' => $a->truck->id, 'matricule' => $a->truck->matricule] : null,
                'provider' => $a->provider ? ['id' => $a->provider->id, 'name' => $a->provider->name] : null,
            ])->toArray(),
            'trips' => $trips->through(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'provider_date' => $t->provider_date,
                'client_date' => $t->client_date,
                'provider_net_weight' => $t->provider_net_weight,
                'client_net_weight' => $t->client_net_weight,
                'gap' => $t->gap,
                'product' => $t->product,
                'driver' => $t->driver ? ['id' => $t->driver->id, 'name' => $t->driver->name] : null,
                'truck' => $t->truck ? ['id' => $t->truck->id, 'matricule' => $t->truck->matricule] : null,
                'provider' => $t->provider ? ['id' => $t->provider->id, 'name' => $t->provider->name] : null,
            ]),
            'drivers' => $drivers->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->toArray(),
            'trucks' => $trucks->map(fn ($t) => ['id' => $t->id, 'matricule' => $t->matricule])->toArray(),
            'providers' => $providers->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->toArray(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }
}
