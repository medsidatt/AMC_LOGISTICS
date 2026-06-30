<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\CapacityCalculatorInterface;
use App\Enums\OperationalParameterKey;
use App\Services\OperationalParameterService;

/**
 * Capacity rules. Reproduces the "default_capacity_tonnage ?: fallback" logic
 * duplicated across DriverKpiService, TruckKpiService, FleetKpiService,
 * FleetCapacityService and FleetOptimizerService — one owner now.
 *
 * NOTE: migrating consumers onto this calculator requires the
 * `default_capacity_tonnage` parameter to mirror the live operator value first
 * (see R1.3 report — FleetSetting→OperationalParameter consolidation).
 *
 * Consumes only OperationalParameterService. No Eloquent, SQL, config, events.
 */
class CapacityCalculator implements CapacityCalculatorInterface
{
    public function __construct(private readonly OperationalParameterService $parameters) {}

    public function defaultCapacity(): float
    {
        return $this->parameters->float(OperationalParameterKey::DEFAULT_CAPACITY);
    }

    public function truckCapacity(?float $perTruckCapacity): float
    {
        return ($perTruckCapacity !== null && $perTruckCapacity > 0.0)
            ? $perTruckCapacity
            : $this->defaultCapacity();
    }
}
