<?php

namespace App\Domain\Operations\ReadModels;

use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use App\Domain\Operations\ReadModels\Data\DriverPeriodAggregate;
use App\Domain\Operations\ReadModels\Data\MonthlyTonnage;
use App\Domain\Operations\ReadModels\Data\PeriodTotals;
use App\Domain\Operations\ReadModels\Data\TruckPeriodAggregate;
use App\Models\TransportTracking;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only projections over `transport_trackings`.
 *
 * Only joins/aggregates/filters/normalizes — no calculation, no threshold, no
 * Operational Parameter, no event. Reproduces the existing inline aggregations
 * (FleetKpiService / DriverKpiService / TrackingDashboardController / DashboardDataService)
 * so consumers can migrate onto it with identical results.
 */
class TransportTrackingReadModel implements TransportTrackingReadModelInterface
{
    public function aggregateByTruck(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return TransportTracking::query()
            ->select('truck_id')
            ->selectRaw('COUNT(*) as rotations')
            ->selectRaw('COALESCE(SUM(client_net_weight), 0) as client_tonnage')
            ->selectRaw('COALESCE(SUM(provider_net_weight), 0) as provider_tonnage')
            ->selectRaw('COALESCE(SUM(client_net_weight - provider_net_weight), 0) as gap_tonnage')
            ->whereBetween('client_date', [$from, $to])
            ->whereNotNull('truck_id')
            ->groupBy('truck_id')
            ->get()
            ->map(fn ($r): TruckPeriodAggregate => new TruckPeriodAggregate(
                (int) $r->truck_id,
                (int) $r->rotations,
                (float) $r->client_tonnage,
                (float) $r->provider_tonnage,
                (float) $r->gap_tonnage,
            ))
            ->values();
    }

    public function aggregateByDriver(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return TransportTracking::query()
            ->select('driver_id')
            ->selectRaw('COUNT(*) as rotations')
            ->selectRaw('COALESCE(SUM(client_net_weight), 0) as client_tonnage')
            ->selectRaw('COALESCE(SUM(provider_net_weight), 0) as provider_tonnage')
            ->selectRaw('COALESCE(SUM(client_net_weight - provider_net_weight), 0) as gap_tonnage')
            ->whereBetween('client_date', [$from, $to])
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->get()
            ->map(fn ($r): DriverPeriodAggregate => new DriverPeriodAggregate(
                (int) $r->driver_id,
                (int) $r->rotations,
                (float) $r->client_tonnage,
                (float) $r->provider_tonnage,
                (float) $r->gap_tonnage,
            ))
            ->values();
    }

    public function periodTotals(CarbonInterface $from, CarbonInterface $to): PeriodTotals
    {
        $row = TransportTracking::query()
            ->whereBetween('client_date', [$from, $to])
            ->selectRaw('COUNT(*) as trips')
            ->selectRaw('COALESCE(SUM(provider_net_weight), 0) as provider_tonnage')
            ->selectRaw('COALESCE(SUM(client_net_weight), 0) as client_tonnage')
            ->selectRaw('COALESCE(SUM(client_net_weight - provider_net_weight), 0) as gap_tonnage')
            ->first();

        return new PeriodTotals(
            (int) $row->trips,
            (float) $row->provider_tonnage,
            (float) $row->client_tonnage,
            (float) $row->gap_tonnage,
        );
    }

    public function monthlyTonnage(int $fiscalMonthStartDay, CarbonInterface $from): Collection
    {
        return TransportTracking::query()
            ->selectRaw(
                "CASE WHEN DAY(client_date) >= ? "
                ."THEN DATE_FORMAT(DATE_ADD(client_date, INTERVAL 1 MONTH), '%Y-%m') "
                ."ELSE DATE_FORMAT(client_date, '%Y-%m') END as ym, "
                .'COALESCE(SUM(provider_net_weight), 0) as provider_tonnage, '
                .'COALESCE(SUM(client_net_weight), 0) as client_tonnage, '
                .'COALESCE(SUM(client_net_weight - provider_net_weight), 0) as gap_tonnage, '
                .'COUNT(*) as trips',
                [$fiscalMonthStartDay],
            )
            ->where('client_date', '>=', $from)
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($r): MonthlyTonnage => new MonthlyTonnage(
                (string) $r->ym,
                (float) $r->provider_tonnage,
                (float) $r->client_tonnage,
                (float) $r->gap_tonnage,
                (int) $r->trips,
            ))
            ->values();
    }
}
