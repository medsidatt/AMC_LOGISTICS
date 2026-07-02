<?php

namespace App\Domain\Operations\ReadModels;

use App\Domain\Operations\Contracts\FleetiConsumptionReadModelInterface;
use App\Domain\Operations\ReadModels\Data\MonthlyConsumptionPoint;
use App\Domain\Operations\ReadModels\Data\TruckConsumptionProjection;
use App\Models\FleetiDailyRecord;
use App\Models\Truck;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only projections over the fleet's RAW Fleeti fuel telemetry.
 *
 * A pure query layer: it aggregates the persisted `fleeti_daily_records` into per-truck and
 * per-month sums/counts and maps them directly. It derives no ratio (no L/100km), applies no
 * threshold, reads no parameter, and emits no event — efficiency computation and interpretation
 * belong to a Domain Calculator, never to this Read Model.
 */
class FleetiConsumptionReadModel implements FleetiConsumptionReadModelInterface
{
    public function truckConsumption(DateTimeImmutable $from, DateTimeImmutable $to): Collection
    {
        // Query 1 — active roster (stable label + ordering), independent of telemetry presence.
        $trucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get(['id', 'matricule']);

        // Query 2 — per-truck telemetry aggregates in range, grouped in SQL (no N+1).
        $agg = FleetiDailyRecord::query()
            ->whereBetween('record_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->selectRaw('truck_id')
            ->selectRaw('COUNT(*) AS recorded_days')
            ->selectRaw('COALESCE(SUM(kilometers),0) AS km')
            ->selectRaw('COALESCE(SUM(consumed),0) AS consumed')
            ->selectRaw('COALESCE(SUM(refills_count),0) AS refills_count')
            ->selectRaw('COALESCE(SUM(refills_volume),0) AS refills_volume')
            ->selectRaw('MAX(record_date) AS last_record')
            ->groupBy('truck_id')
            ->get()
            ->keyBy('truck_id');

        return $trucks->map(function (Truck $t) use ($agg): TruckConsumptionProjection {
            $row = $agg[$t->id] ?? null;

            return new TruckConsumptionProjection(
                (int) $t->id,
                (string) $t->matricule,
                $row !== null ? (int) $row->recorded_days : 0,
                $row !== null ? (float) $row->km : 0.0,
                $row !== null ? (float) $row->consumed : 0.0,
                $row !== null ? (int) $row->refills_count : 0,
                $row !== null ? (float) $row->refills_volume : 0.0,
                ($row?->last_record) !== null ? new DateTimeImmutable((string) $row->last_record) : null,
            );
        })->values();
    }

    public function monthlyConsumption(DateTimeImmutable $from, DateTimeImmutable $to): Collection
    {
        return FleetiDailyRecord::query()
            ->whereBetween('record_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->selectRaw("DATE_FORMAT(record_date, '%Y-%m') AS month")
            ->selectRaw('COUNT(*) AS recorded_days')
            ->selectRaw('COALESCE(SUM(kilometers),0) AS km')
            ->selectRaw('COALESCE(SUM(consumed),0) AS consumed')
            ->selectRaw('COALESCE(SUM(refills_volume),0) AS refills_volume')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r): MonthlyConsumptionPoint => new MonthlyConsumptionPoint(
                (string) $r->month,
                (int) $r->recorded_days,
                (float) $r->km,
                (float) $r->consumed,
                (float) $r->refills_volume,
            ));
    }
}
