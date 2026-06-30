<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns maintenance level/status calculations. Pure over the supplied kilometres;
 * the warning ratio comes from OperationalParameterService. No Eloquent, SQL, config, events.
 */
interface MaintenanceCalculatorInterface
{
    /** Absolute-threshold status: red (overdue) / yellow (within warning) / green. */
    public function statusFromRemaining(float $remainingKm, float $warningThresholdKm): string;

    /** Interval-ratio level: red (overdue) / orange (within warning ratio of interval) / green. */
    public function level(float $remainingKm, float $intervalKm): string;
}
