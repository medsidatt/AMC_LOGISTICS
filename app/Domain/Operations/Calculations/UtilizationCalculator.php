<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\UtilizationCalculatorInterface;

/**
 * Load-rate calculation — the single owner of `tonnage / (capacity × rotations)`,
 * duplicated today in TruckKpiService and FleetKpiService (per-truck and fleet level).
 * Pure: the caller passes the capacity it resolved. No Eloquent, SQL, config, events.
 */
class UtilizationCalculator implements UtilizationCalculatorInterface
{
    public function loadRate(float $tonnage, float $capacity, int $rotations): float
    {
        return $rotations > 0 ? $tonnage / ($capacity * $rotations) : 0.0;
    }
}
