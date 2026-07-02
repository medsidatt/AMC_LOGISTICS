<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\MaintenanceCalculatorInterface;
use App\Enums\OperationalParameterKey;
use App\Services\OperationalParameterService;

/**
 * Maintenance level/status. Single owner of the two level rules previously inlined in
 * MaintenanceStatusService (absolute warning threshold) and TruckKpiService (interval
 * ratio). The ratio is a parameter (no hardcoded rule). No Eloquent, SQL, config, events.
 */
class MaintenanceCalculator implements MaintenanceCalculatorInterface
{
    public function __construct(private readonly OperationalParameterService $parameters) {}

    public function statusFromRemaining(float $remainingKm, float $warningThresholdKm): string
    {
        if ($remainingKm <= 0) {
            return 'red';
        }

        if ($remainingKm <= $warningThresholdKm) {
            return 'yellow';
        }

        return 'green';
    }

    public function level(float $remainingKm, float $intervalKm): string
    {
        if ($remainingKm <= 0) {
            return 'red';
        }

        if ($remainingKm <= ($intervalKm * $this->parameters->float(OperationalParameterKey::MAINTENANCE_WARNING_RATIO))) {
            return 'orange';
        }

        return 'green';
    }

    public function isKilometersOverdue(float $totalKilometers, ?float $lastMaintenanceKm, ?float $perTruckIntervalKm): bool
    {
        $interval = $perTruckIntervalKm ?? $this->parameters->float(OperationalParameterKey::MAX_KM_BEFORE_MAINTENANCE);
        $nextServiceKm = ($lastMaintenanceKm ?? 0.0) + $interval;

        return $totalKilometers >= $nextServiceKm;
    }
}
