<?php

namespace App\Domain\Operations\ReadModels\Data;

use DateTimeImmutable;

/**
 * Immutable projection of one truck's RAW fuel-recharge facts over a period.
 *
 * Raw stored values only — the sums/counts of the truck's persisted `fuel_card_transactions`
 * (recharge amount, the policy's stored `kpi_eligible` flag, estimated litres) and the last
 * recharge timestamp. It carries NO derived value (no cost-per-tonne, no litres/100km, no budget
 * variance, no threshold): computing efficiency/cost ratios and deciding over/under budget are a
 * Domain Calculator's job, never the Read Model's.
 */
final readonly class TruckFuelProjection
{
    public function __construct(
        public int $truckId,
        public string $matricule,
        public int $rechargeCount,
        public float $totalSpendFcfa,
        public float $kpiEligibleSpendFcfa,
        public float $estimatedLitres,
        public ?DateTimeImmutable $lastRechargeAt,
    ) {}
}
