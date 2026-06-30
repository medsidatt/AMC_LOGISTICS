<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns capacity resolution. The single source for the fleet default capacity
 * and per-truck capacity fallback. Consumes only OperationalParameterService.
 */
interface CapacityCalculatorInterface
{
    /** Fleet-wide default capacity per rotation, in tonnes. */
    public function defaultCapacity(): float;

    /** Per-truck capacity if set (> 0), else the fleet default. */
    public function truckCapacity(?float $perTruckCapacity): float;
}
