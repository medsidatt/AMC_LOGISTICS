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

    /**
     * Whether a kilometre-tracked truck has reached or passed its next service. Owns the
     * distance-to-service decision: it derives the next-service odometer from the last
     * service km and the per-truck interval (falling back to the fleet max-km parameter when
     * the truck carries no interval) and compares it to the current odometer.
     */
    public function isKilometersOverdue(float $totalKilometers, ?float $lastMaintenanceKm, ?float $perTruckIntervalKm): bool;
}
