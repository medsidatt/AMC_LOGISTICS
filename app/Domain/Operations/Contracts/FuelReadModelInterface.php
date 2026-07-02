<?php

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\ReadModels\Data\FuelImportBatchProjection;
use App\Domain\Operations\ReadModels\Data\FuelReviewQueueStats;
use App\Domain\Operations\ReadModels\Data\FuelSourceSlice;
use App\Domain\Operations\ReadModels\Data\MonthlyFuelSpendPoint;
use App\Domain\Operations\ReadModels\Data\TruckFuelProjection;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Business projections over the active fleet's RAW fuel-recharge facts (the persisted
 * `fuel_card_transactions` ledger). Returns immutable DTOs of stored values only (recharge
 * count, spend in FCFA, the policy's stored `kpi_eligible` flag, estimated litres, last recharge
 * date); it never derives cost-per-tonne / litres-per-100km and never applies a budget threshold
 * (that is a Domain Calculator's job). The only component that reads the fuel state of the fleet
 * for this concern. It performs NO classification — `transaction_type` / `kpi_eligible` are read
 * as stored facts produced upstream by the ClassificationPolicy at import.
 */
interface FuelReadModelInterface
{
    /**
     * Raw per-truck fuel-recharge aggregates for FUEL_RECHARGE rows in [$from, $to]
     * (no derived or decided values).
     *
     * @return Collection<int, TruckFuelProjection>
     */
    public function truckFuelSpend(DateTimeImmutable $from, DateTimeImmutable $to): Collection;

    /**
     * Raw per-calendar-month FUEL_RECHARGE aggregates in [$from, $to], oldest → newest.
     *
     * @return Collection<int, MonthlyFuelSpendPoint>
     */
    public function monthlySpend(DateTimeImmutable $from, DateTimeImmutable $to): Collection;

    /**
     * Raw (source, transaction_type) count/amount slices of the whole ledger in [$from, $to].
     *
     * @return Collection<int, FuelSourceSlice>
     */
    public function sourceDistribution(DateTimeImmutable $from, DateTimeImmutable $to): Collection;

    /** Stored review-status tallies of the ledger (counts of what was already decided upstream). */
    public function reviewQueueStats(): FuelReviewQueueStats;

    /**
     * Stored import batches, newest first (audit trail rows as-is).
     *
     * @return Collection<int, FuelImportBatchProjection>
     */
    public function importHistory(int $limit = 50): Collection;
}
