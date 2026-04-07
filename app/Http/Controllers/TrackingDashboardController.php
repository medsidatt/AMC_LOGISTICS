<?php

namespace App\Http\Controllers;

use App\Models\KilometerTracking;
use App\Models\TransportTracking;
use App\Models\Driver;
use App\Models\Provider;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingDashboardController extends Controller
{
    protected int $gapThresholdKg = 150;

    /**
     * Get the start date for a custom month period (22nd to 21st).
     * "Current month" = 22nd of previous calendar month to 21st of this calendar month.
     */
    private function periodStart(?string $input = null): Carbon
    {
        if ($input) return Carbon::parse($input)->startOfDay();
        // Default: 3 months back from current period start
        $start = now()->day >= 22
            ? now()->copy()->setDay(22)->startOfDay()
            : now()->copy()->subMonth()->setDay(22)->startOfDay();
        return $start->subMonths(3);
    }

    private function periodEnd(?string $input = null): Carbon
    {
        if ($input) return Carbon::parse($input)->endOfDay();
        // Default: end of current period
        return now()->day >= 22
            ? now()->copy()->addMonth()->setDay(21)->endOfDay()
            : now()->copy()->setDay(21)->endOfDay();
    }

    public function index(Request $req)
    {
        $from = $this->periodStart($req->input('from'));
        $to   = $this->periodEnd($req->input('to'));

        $driverId   = $req->input('driver_id');
        $truckId    = $req->input('truck_id');
        $providerId = $req->input('provider_id');
        $product    = $req->input('product');
        $base       = $req->input('base');

        $q = TransportTracking::query()
            ->whereBetween('client_date', [$from, $to]);

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

        // Month = 22nd of previous month to 21st of current month
        $cmStart = now()->day >= 22
            ? now()->copy()->setDay(22)->startOfDay()
            : now()->copy()->subMonth()->setDay(22)->startOfDay();
        $cmEnd = now()->day >= 22
            ? now()->copy()->addMonth()->setDay(21)->endOfDay()
            : now()->copy()->setDay(21)->endOfDay();

        // Build a filtered base query (same filters, without date range)
        $qFiltered = TransportTracking::query();
        if ($driverId)   $qFiltered->where('driver_id', $driverId);
        if ($truckId)    $qFiltered->where('truck_id', $truckId);
        if ($providerId) $qFiltered->where('provider_id', $providerId);
        if ($product)    $qFiltered->where('product', $product);
        if ($base)       $qFiltered->where('base', $base);

        $thisMonthTonnage = (clone $qFiltered)
            ->whereBetween('client_date', [$cmStart, $cmEnd])
            ->sum('client_net_weight');

        $thisYearTonnage = (clone $qFiltered)
            ->whereYear('client_date', now()->year)
            ->sum('client_net_weight');

        // --- Charts data ---

        // Monthly tonnage (provider vs client)
        // Monthly grouped by custom periods (22nd to 21st) using client_date
        $monthlyRaw = (clone $q)
            ->selectRaw("DATE_FORMAT(DATE_ADD(client_date, INTERVAL 10 DAY), '%Y-%m') as ym, SUM(provider_net_weight) as prov, SUM(client_net_weight) as client, SUM(ABS(provider_net_weight - client_net_weight)) as gap_sum, COUNT(*) as trips")
            ->whereNotNull('client_date')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $months           = $monthlyRaw->pluck('ym')->map(fn($ym) => Carbon::createFromFormat('Y-m', $ym)->translatedFormat('M Y'))->toArray();
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
            ->orderByDesc('client_date')
            ->limit(50)
            ->get();

        // Trips paginated
        $trips = (clone $q)
            ->with(['driver', 'truck', 'provider'])
            ->orderByDesc('client_date')
            ->paginate(25)
            ->appends($req->query());

        // Filter lists
        $drivers   = Driver::orderBy('name')->get();
        $trucks    = Truck::orderBy('matricule')->get();
        $providers = Provider::orderBy('name')->get();

        // ── Fleeti Fleet Data ──
        $allTrucks = Truck::where('is_active', true)->get();
        $fleeti = [
            'total_trucks' => $allTrucks->count(),
            'connected' => $allTrucks->whereNotNull('fleeti_asset_id')->count(),
            'synced_recently' => $allTrucks->filter(fn ($t) =>
                $t->fleeti_last_synced_at && $t->fleeti_last_synced_at->gt(now()->subHours(2))
            )->count(),
            'total_fleet_km' => round((float) $allTrucks->sum('total_kilometers'), 0),
            'avg_km_per_truck' => $allTrucks->count() > 0
                ? round((float) $allTrucks->avg('total_kilometers'), 0) : 0,
            'last_sync' => $allTrucks->max('fleeti_last_synced_at')
                ? Carbon::parse($allTrucks->max('fleeti_last_synced_at'))->format('d/m/Y H:i') : null,
            'trucks' => $allTrucks->sortByDesc('total_kilometers')->take(15)->values()->map(fn ($t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'total_km' => round((float) $t->total_kilometers, 0),
                'fleeti_connected' => !empty($t->fleeti_asset_id),
                'last_synced' => $t->fleeti_last_synced_at?->format('d/m/Y H:i'),
                'fleeti_km' => $t->fleeti_last_kilometers ? round((float) $t->fleeti_last_kilometers, 0) : null,
            ])->toArray(),
            // Daily km evolution last 14 days
            'daily_km' => KilometerTracking::select(
                    DB::raw("date as raw_date"),
                    DB::raw("DATE_FORMAT(date, '%d/%m') as day"),
                    DB::raw('SUM(kilometers) as total_km'),
                    DB::raw('COUNT(DISTINCT truck_id) as trucks_active')
                )
                ->where('date', '>=', now()->subDays(14))
                ->groupBy('raw_date', 'day')
                ->orderBy('raw_date')
                ->get()
                ->map(fn ($r) => [
                    'day' => $r->day,
                    'km' => round((float) $r->total_km, 0),
                    'trucks' => (int) $r->trucks_active,
                ])->toArray(),
        ];

        return \Inertia\Inertia::render('transport-trackings/Reports', [
            'totalTrips' => (int) ($totalTrips ?? 0),
            'totalProviderWeight' => round((float) ($totalProviderWeight ?? 0), 2),
            'totalClientWeight' => round((float) ($totalClientWeight ?? 0), 2),
            'totalGap' => round((float) ($totalGap ?? 0), 2),
            'totalDiscrepanciesCount' => (int) ($totalDiscrepanciesCount ?? 0),
            'totalDiscrepancyKg' => round((float) ($totalDiscrepancyKg ?? 0), 2),
            'suspiciousDrivers' => (int) ($suspiciousDrivers ?? 0),
            'thisMonthTonnage' => round((float) ($thisMonthTonnage ?? 0), 2),
            'thisYearTonnage' => round((float) ($thisYearTonnage ?? 0), 2),
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
                'provider_date' => $a->provider_date?->format('d/m/Y'),
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
                'provider_date' => $t->provider_date?->format('d/m/Y'),
                'client_date' => $t->client_date?->format('d/m/Y'),
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
            'products' => [
                ['id' => '0/3', 'name' => '0/3'],
                ['id' => '3/8', 'name' => '3/8'],
                ['id' => '8/16', 'name' => '8/16'],
            ],
            'bases' => [
                ['id' => 'mr', 'name' => 'Mauritanie'],
                ['id' => 'sn', 'name' => 'Sénégal'],
            ],
            'filters' => array_filter([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'driver_id' => $driverId,
                'truck_id' => $truckId,
                'provider_id' => $providerId,
                'product' => $product,
                'base' => $base,
            ]),
        ]);
    }

    /**
     * Fleeti & Fuel dashboard
     */
    public function fleeti()
    {
        $allTrucks = Truck::where('is_active', true)->orderBy('matricule')->get();

        $connected = $allTrucks->whereNotNull('fleeti_asset_id');
        $syncedRecently = $connected->filter(fn ($t) =>
            $t->fleeti_last_synced_at && $t->fleeti_last_synced_at->gt(now()->subHours(2))
        );

        // Fuel levels from Fleeti
        $fuelData = $allTrucks->filter(fn ($t) => $t->fleeti_last_fuel_level !== null)->map(fn ($t) => [
            'id' => $t->id,
            'matricule' => $t->matricule,
            'fuel_level' => round((float) $t->fleeti_last_fuel_level, 1),
            'total_km' => round((float) $t->total_kilometers, 0),
            'last_synced' => $t->fleeti_last_synced_at?->format('d/m/Y H:i'),
        ])->values();

        // Fuel distribution
        $fuelDistribution = [
            'critical' => $fuelData->where('fuel_level', '<', 15)->count(),
            'low' => $fuelData->whereBetween('fuel_level', [15, 30])->count(),
            'medium' => $fuelData->whereBetween('fuel_level', [30, 60])->count(),
            'good' => $fuelData->where('fuel_level', '>=', 60)->count(),
        ];

        // Daily km from kilometer_trackings (last 30 days)
        $dailyKm = KilometerTracking::select(
                DB::raw("date as raw_date"),
                DB::raw("DATE_FORMAT(date, '%d/%m') as day"),
                DB::raw('SUM(kilometers) as total_km'),
                DB::raw('COUNT(DISTINCT truck_id) as trucks_active')
            )
            ->where('date', '>=', now()->subDays(30))
            ->groupBy('raw_date', 'day')
            ->orderBy('raw_date')
            ->get()
            ->map(fn ($r) => [
                'day' => $r->day,
                'km' => round((float) $r->total_km, 0),
                'trucks' => (int) $r->trucks_active,
            ])->toArray();

        // All trucks with Fleeti data
        $fleetTable = $allTrucks->map(fn ($t) => [
            'id' => $t->id,
            'matricule' => $t->matricule,
            'total_km' => round((float) $t->total_kilometers, 0),
            'fleeti_connected' => !empty($t->fleeti_asset_id),
            'fleeti_km' => $t->fleeti_last_kilometers ? round((float) $t->fleeti_last_kilometers, 0) : null,
            'fuel_level' => $t->fleeti_last_fuel_level !== null ? round((float) $t->fleeti_last_fuel_level, 1) : null,
            'last_synced' => $t->fleeti_last_synced_at?->format('d/m/Y H:i'),
        ])->toArray();

        return \Inertia\Inertia::render('analytics/Fleeti', [
            'stats' => [
                'total_trucks' => $allTrucks->count(),
                'connected' => $connected->count(),
                'synced_recently' => $syncedRecently->count(),
                'total_fleet_km' => round((float) $allTrucks->sum('total_kilometers'), 0),
                'avg_km' => $allTrucks->count() > 0 ? round((float) $allTrucks->avg('total_kilometers'), 0) : 0,
                'last_sync' => $allTrucks->max('fleeti_last_synced_at')
                    ? Carbon::parse($allTrucks->max('fleeti_last_synced_at'))->format('d/m/Y H:i') : null,
                'trucks_with_fuel' => $fuelData->count(),
                'avg_fuel' => $fuelData->count() > 0 ? round($fuelData->avg('fuel_level'), 1) : 0,
            ],
            'fuelDistribution' => $fuelDistribution,
            'fuelData' => $fuelData->toArray(),
            'dailyKm' => $dailyKm,
            'fleetTable' => $fleetTable,
        ]);
    }

    /**
     * Rotations & Weight dashboard
     */
    public function rotations(Request $req)
    {
        $from = $this->periodStart($req->input('from'));
        $to = $this->periodEnd($req->input('to'));

        $driverId = $req->input('driver_id');
        $truckId = $req->input('truck_id');
        $providerId = $req->input('provider_id');
        $product = $req->input('product');

        $q = TransportTracking::query()->whereBetween('client_date', [$from, $to]);
        if ($driverId) $q->where('driver_id', $driverId);
        if ($truckId) $q->where('truck_id', $truckId);
        if ($providerId) $q->where('provider_id', $providerId);
        if ($product) $q->where('product', $product);

        $totalTrips = (clone $q)->count();
        $totalProviderWeight = (float) ((clone $q)->sum('provider_net_weight') ?? 0);
        $totalClientWeight = (float) ((clone $q)->sum('client_net_weight') ?? 0);
        $totalGap = $totalClientWeight - $totalProviderWeight;

        // Monthly breakdown (22→21)
        $monthlyRaw = (clone $q)
            ->selectRaw("DATE_FORMAT(DATE_ADD(client_date, INTERVAL 10 DAY), '%Y-%m') as ym, SUM(provider_net_weight) as prov, SUM(client_net_weight) as client, SUM(ABS(provider_net_weight - client_net_weight)) as gap_sum, COUNT(*) as trips")
            ->whereNotNull('client_date')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        // Top trucks by rotations
        $topTrucks = (clone $q)
            ->selectRaw('truck_id, COUNT(*) as trips, SUM(provider_net_weight) as prov, SUM(client_net_weight) as client, SUM(ABS(provider_net_weight - client_net_weight)) as gap_sum')
            ->groupBy('truck_id')
            ->orderByDesc('trips')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'truck' => Truck::withTrashed()->find($r->truck_id)?->matricule ?? 'N/A',
                'trips' => (int) $r->trips,
                'prov' => round((float) $r->prov, 0),
                'client' => round((float) $r->client, 0),
                'gap' => round((float) $r->gap_sum, 0),
            ])->toArray();

        // Top drivers by rotations
        $topDrivers = (clone $q)
            ->selectRaw('driver_id, COUNT(*) as trips, SUM(provider_net_weight) as prov, SUM(client_net_weight) as client, SUM(ABS(provider_net_weight - client_net_weight)) as gap_sum')
            ->groupBy('driver_id')
            ->orderByDesc('trips')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'driver' => Driver::withTrashed()->find($r->driver_id)?->name ?? 'N/A',
                'trips' => (int) $r->trips,
                'prov' => round((float) $r->prov, 0),
                'client' => round((float) $r->client, 0),
                'gap' => round((float) $r->gap_sum, 0),
            ])->toArray();

        // Weight by product
        $byProduct = (clone $q)
            ->selectRaw("product, COUNT(*) as trips, SUM(provider_net_weight) as prov, SUM(client_net_weight) as client")
            ->whereNotNull('product')
            ->groupBy('product')
            ->get()
            ->map(fn ($r) => [
                'product' => $r->product,
                'trips' => (int) $r->trips,
                'prov' => round((float) $r->prov, 0),
                'client' => round((float) $r->client, 0),
            ])->toArray();

        return \Inertia\Inertia::render('analytics/Rotations', [
            'totalTrips' => $totalTrips,
            'totalProviderWeight' => round($totalProviderWeight, 0),
            'totalClientWeight' => round($totalClientWeight, 0),
            'totalGap' => round($totalGap, 0),
            'months' => $monthlyRaw->pluck('ym')->map(fn ($ym) => Carbon::createFromFormat('Y-m', $ym)->translatedFormat('M Y'))->toArray(),
            'monthlyProvider' => $monthlyRaw->pluck('prov')->map(fn ($v) => round((float) $v, 0))->toArray(),
            'monthlyClient' => $monthlyRaw->pluck('client')->map(fn ($v) => round((float) $v, 0))->toArray(),
            'monthlyGap' => $monthlyRaw->pluck('gap_sum')->map(fn ($v) => round((float) $v, 0))->toArray(),
            'monthlyTrips' => $monthlyRaw->pluck('trips')->map(fn ($v) => (int) $v)->toArray(),
            'topTrucks' => $topTrucks,
            'topDrivers' => $topDrivers,
            'byProduct' => $byProduct,
            'drivers' => Driver::orderBy('name')->get(['id', 'name'])->toArray(),
            'trucks' => Truck::orderBy('matricule')->get(['id', 'matricule'])->toArray(),
            'providers' => Provider::orderBy('name')->get(['id', 'name'])->toArray(),
            'filters' => array_filter([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'driver_id' => $driverId,
                'truck_id' => $truckId,
                'provider_id' => $providerId,
                'product' => $product,
            ]),
        ]);
    }
}
