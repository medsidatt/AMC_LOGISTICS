<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\WeightCalculatorInterface;
use App\Enums\OperationalParameterKey;
use App\Services\OperationalParameterService;

/**
 * Weight-gap rules. Reproduces the logic currently scattered across
 * TransportTracking::weightGapThreshold(), DriverKpiService, TruckKpiService,
 * FleetKpiService and TrackingDashboardController — one owner now.
 *
 * Consumes only OperationalParameterService. No Eloquent, SQL, config, events.
 */
class WeightCalculator implements WeightCalculatorInterface
{
    public function __construct(private readonly OperationalParameterService $parameters) {}

    public function gapThreshold(): float
    {
        return $this->parameters->float(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD);
    }

    public function gap(float $providerWeight, float $clientWeight): float
    {
        return $clientWeight - $providerWeight;
    }

    public function isGapViolation(float $providerWeight, float $clientWeight): bool
    {
        return abs($clientWeight - $providerWeight) > $this->gapThreshold();
    }
}
