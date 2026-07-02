<?php

namespace App\Domain\Operations\ReadModels\Data;

/**
 * Immutable projection of one calendar month's RAW EDK fuel-recharge aggregates.
 *
 * Stored sums/counts only (recharge count, FCFA spend, the policy's stored kpi_eligible spend,
 * estimated litres). No derived value, no comparison, no target — trend interpretation belongs
 * to a Calculator, never a Read Model.
 */
final readonly class MonthlyFuelSpendPoint
{
    public function __construct(
        public string $month,               // 'YYYY-MM'
        public int $rechargeCount,
        public float $spendFcfa,
        public float $kpiEligibleSpendFcfa,
        public float $estimatedLitres,
    ) {}
}
