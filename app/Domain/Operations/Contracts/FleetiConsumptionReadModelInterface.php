<?php

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\ReadModels\Data\MonthlyConsumptionPoint;
use App\Domain\Operations\ReadModels\Data\TruckConsumptionProjection;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Business projections over the fleet's RAW Fleeti fuel telemetry (the persisted
 * `fleeti_daily_records`). Returns immutable DTOs of stored sums/counts only (recorded days,
 * kilometres, consumed litres, refills); it never derives L/100km or an efficiency verdict
 * (that is a Domain Calculator's job — and per the certified data audit, per-day division is
 * unreliable on near-zero-km days). The only component that reads Fleeti telemetry for this
 * concern.
 */
interface FleetiConsumptionReadModelInterface
{
    /**
     * Raw per-truck telemetry aggregates for records in [$from, $to] (no derived values).
     *
     * @return Collection<int, TruckConsumptionProjection>
     */
    public function truckConsumption(DateTimeImmutable $from, DateTimeImmutable $to): Collection;

    /**
     * Raw per-calendar-month telemetry aggregates in [$from, $to], oldest → newest.
     *
     * @return Collection<int, MonthlyConsumptionPoint>
     */
    public function monthlyConsumption(DateTimeImmutable $from, DateTimeImmutable $to): Collection;
}
