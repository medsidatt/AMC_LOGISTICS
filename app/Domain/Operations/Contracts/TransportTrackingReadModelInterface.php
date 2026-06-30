<?php

namespace App\Domain\Operations\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Business projections over transport activity (loads / tickets).
 * Returns immutable DTOs; never KPIs, thresholds, or Eloquent models.
 * Callers pass already-resolved period bounds and the fiscal start day —
 * the Read Model never reads Operational Parameters.
 */
interface TransportTrackingReadModelInterface
{
    /** @return Collection<int, \App\Domain\Operations\ReadModels\Data\TruckPeriodAggregate> */
    public function aggregateByTruck(CarbonInterface $from, CarbonInterface $to): Collection;

    /** @return Collection<int, \App\Domain\Operations\ReadModels\Data\DriverPeriodAggregate> */
    public function aggregateByDriver(CarbonInterface $from, CarbonInterface $to): Collection;

    public function periodTotals(CarbonInterface $from, CarbonInterface $to): \App\Domain\Operations\ReadModels\Data\PeriodTotals;

    /** @return Collection<int, \App\Domain\Operations\ReadModels\Data\MonthlyTonnage> */
    public function monthlyTonnage(int $fiscalMonthStartDay, CarbonInterface $from): Collection;
}
