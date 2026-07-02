<?php

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\ReadModels\Data\DriverPeriodAggregate;
use App\Domain\Operations\ReadModels\Data\LoadProjection;
use App\Domain\Operations\ReadModels\Data\MonthlyTonnage;
use App\Domain\Operations\ReadModels\Data\PeriodTotals;
use App\Domain\Operations\ReadModels\Data\TruckPeriodAggregate;
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
    /** @return Collection<int, TruckPeriodAggregate> */
    public function aggregateByTruck(CarbonInterface $from, CarbonInterface $to): Collection;

    /** @return Collection<int, DriverPeriodAggregate> */
    public function aggregateByDriver(CarbonInterface $from, CarbonInterface $to): Collection;

    public function periodTotals(CarbonInterface $from, CarbonInterface $to): PeriodTotals;

    /** @return Collection<int, MonthlyTonnage> */
    public function monthlyTonnage(int $fiscalMonthStartDay, CarbonInterface $from): Collection;

    /**
     * Per-load weighing facts over a period (by client date). Raw provider/client net
     * weights only — the gap threshold / violation test belongs to the WeightCalculator.
     *
     * @return Collection<int, LoadProjection>
     */
    public function loads(CarbonInterface $from, CarbonInterface $to): Collection;
}
