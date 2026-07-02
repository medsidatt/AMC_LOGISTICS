<?php

namespace App\Domain\Operations\ReadModels;

use App\Domain\Operations\Contracts\InspectionReadModelInterface;
use App\Domain\Operations\ReadModels\Data\TruckInspectionProjection;
use App\Models\InspectionChecklist;
use App\Models\Truck;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only projections over `inspection_checklists`, joined to the active fleet.
 *
 * Normalizes the "last inspection per truck" read that the overdue-inspection detection
 * re-queries today (DashboardDataService / HseController use the same `inspection_date`
 * recency check). Exposes the RAW last date only — deciding expiry against the SLA is the
 * InspectionCalculator's job. No calculation, no parameter, no event.
 */
class InspectionReadModel implements InspectionReadModelInterface
{
    public function lastInspectionByActiveTruck(): Collection
    {
        // One grouped read of the latest inspection date per truck.
        $lastDates = InspectionChecklist::query()
            ->selectRaw('truck_id, MAX(inspection_date) as last_date')
            ->groupBy('truck_id')
            ->pluck('last_date', 'truck_id');

        return Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get(['id', 'matricule'])
            ->map(function (Truck $t) use ($lastDates): TruckInspectionProjection {
                $raw = $lastDates[$t->id] ?? null;

                return new TruckInspectionProjection(
                    (int) $t->id,
                    (string) $t->matricule,
                    $raw !== null ? new DateTimeImmutable((string) $raw) : null,
                );
            })
            ->values();
    }
}
