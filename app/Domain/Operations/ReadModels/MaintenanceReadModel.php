<?php

namespace App\Domain\Operations\ReadModels;

use App\Domain\Operations\Contracts\MaintenanceReadModelInterface;
use App\Domain\Operations\ReadModels\Data\TruckMaintenanceProjection;
use App\Models\Maintenance;
use App\Models\Truck;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only projections over the active fleet's RAW maintenance fields.
 *
 * A pure query layer: it reads the truck's stored maintenance columns (odometer, tracking
 * mode, interval) and the latest maintenance record (kilometres/date), and maps them
 * directly. It calls NO Truck aggregate behaviour (no remainingKm/remainingRotations/
 * usesKilometerMaintenance/…): computing distance-to-service and deciding red/overdue belong
 * to the MaintenanceCalculator. No calculation, no threshold, no default, no parameter, no
 * event.
 */
class MaintenanceReadModel implements MaintenanceReadModelInterface
{
    public function activeTrucksMaintenance(): Collection
    {
        // Query 1 — active roster with the raw maintenance columns stored on the truck.
        $trucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get(['id', 'matricule', 'maintenance_type', 'total_kilometers', 'km_maintenance_interval']);

        // Query 2 — the latest maintenance record per truck, grouped in memory (no N+1).
        $latest = Maintenance::query()
            ->whereIn('truck_id', $trucks->pluck('id'))
            ->orderBy('truck_id')
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->get(['truck_id', 'maintenance_date', 'kilometers_at_maintenance'])
            ->groupBy('truck_id')
            ->map(fn (Collection $rows): Maintenance => $rows->first());

        return $trucks->map(function (Truck $t) use ($latest): TruckMaintenanceProjection {
            $service = $latest[$t->id] ?? null;

            return new TruckMaintenanceProjection(
                (int) $t->id,
                (string) $t->matricule,
                (string) $t->maintenance_type,
                (float) $t->total_kilometers,
                $t->km_maintenance_interval !== null ? (float) $t->km_maintenance_interval : null,
                $service?->kilometers_at_maintenance !== null ? (float) $service->kilometers_at_maintenance : null,
                $service?->maintenance_date !== null ? new DateTimeImmutable((string) $service->maintenance_date) : null,
            );
        })->values();
    }
}
