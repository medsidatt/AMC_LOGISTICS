<?php

namespace App\Domain\Operations\ReadModels;

use App\Domain\Operations\Contracts\FleetReadModelInterface;
use App\Domain\Operations\ReadModels\Data\TruckProjection;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only projections over `trucks`.
 *
 * Normalizes the active-fleet roster the rest of the platform re-queries 7+ times.
 * Exposes RAW per-truck capacity/target (nullable) — resolving the global default
 * is a Domain Calculator's job. No calculation, no parameter, no event.
 */
class FleetReadModel implements FleetReadModelInterface
{
    public function activeTrucks(): Collection
    {
        return $this->project($this->activeQuery());
    }

    public function activeAvailableTrucks(): Collection
    {
        return $this->project($this->activeQuery()->where('is_available', true));
    }

    public function activeTruckCount(): int
    {
        return $this->activeQuery()->count();
    }

    public function availableCapacityTonnage(): float
    {
        return (float) $this->activeQuery()->where('is_available', true)->sum('capacity_tonnage');
    }

    private function activeQuery(): Builder
    {
        return Truck::query()->where('is_active', true)->orderBy('matricule');
    }

    private function project(Builder $query): Collection
    {
        return $query
            ->get(['id', 'matricule', 'capacity_tonnage', 'target_rotations_per_week', 'is_available', 'availability_factor', 'maintenance_factor', 'transporter_id'])
            ->map(fn (Truck $t): TruckProjection => new TruckProjection(
                (int) $t->id,
                (string) $t->matricule,
                $t->capacity_tonnage !== null ? (float) $t->capacity_tonnage : null,
                $t->target_rotations_per_week !== null ? (int) $t->target_rotations_per_week : null,
                (bool) $t->is_available,
                $t->availability_factor !== null ? (float) $t->availability_factor : null,
                $t->maintenance_factor !== null ? (float) $t->maintenance_factor : null,
                $t->transporter_id !== null ? (int) $t->transporter_id : null,
            ))
            ->values();
    }
}
