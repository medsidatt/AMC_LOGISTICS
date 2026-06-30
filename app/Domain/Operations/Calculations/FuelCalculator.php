<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\FuelCalculatorInterface;

/**
 * Fuel efficiency. Single owner of the litres/tonne yield duplicated in TruckKpiService
 * and FleetKpiService. Pure — no Eloquent, SQL, config, or events.
 */
class FuelCalculator implements FuelCalculatorInterface
{
    public function yieldPerTonne(float $litres, float $tonnage): ?float
    {
        return $tonnage > 0 ? $litres / $tonnage : null;
    }
}
