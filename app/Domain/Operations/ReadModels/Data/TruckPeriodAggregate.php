<?php

namespace App\Domain\Operations\ReadModels\Data;

/** Immutable per-truck aggregate of transport activity over a period. Raw sums only. */
final readonly class TruckPeriodAggregate
{
    public function __construct(
        public int $truckId,
        public int $rotations,
        public float $clientTonnage,
        public float $providerTonnage,
        public float $gapTonnage,
    ) {}
}
