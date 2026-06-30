<?php

namespace App\Domain\Operations\Contracts;

use Illuminate\Support\Collection;

/**
 * Business projections over fleet state (trucks).
 * Returns immutable DTOs of raw per-truck values; never applies the global
 * capacity/target default (that is a Domain Calculator's job).
 */
interface FleetReadModelInterface
{
    /** @return Collection<int, \App\Domain\Operations\ReadModels\Data\TruckProjection> */
    public function activeTrucks(): Collection;

    /** @return Collection<int, \App\Domain\Operations\ReadModels\Data\TruckProjection> */
    public function activeAvailableTrucks(): Collection;

    public function activeTruckCount(): int;

    public function availableCapacityTonnage(): float;
}
