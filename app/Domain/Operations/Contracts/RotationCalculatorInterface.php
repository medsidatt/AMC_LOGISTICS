<?php

namespace App\Domain\Operations\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Owns rotation aggregation and fiscal-period handling. Aggregation only — no scoring,
 * no thresholds. Consumes TransportTrackingReadModel + OperationalParameterService.
 * Never queries Eloquent.
 */
interface RotationCalculatorInterface
{
    /** @return Collection<int, \App\Domain\Operations\ReadModels\Data\TruckPeriodAggregate> */
    public function byTruck(CarbonInterface $from, CarbonInterface $to): Collection;

    /** @return Collection<int, \App\Domain\Operations\ReadModels\Data\DriverPeriodAggregate> */
    public function byDriver(CarbonInterface $from, CarbonInterface $to): Collection;

    /** Monthly tonnage grouped by the configured fiscal-month start day. */
    public function monthlyTonnage(CarbonInterface $from): Collection;

    public function fleetTotals(CarbonInterface $from, CarbonInterface $to): \App\Domain\Operations\ReadModels\Data\PeriodTotals;
}
