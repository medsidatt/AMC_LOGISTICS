<?php

namespace App\Domain\Operations\ReadModels\Data;

/** Immutable per-driver aggregate of transport activity over a period. Raw sums only. */
final readonly class DriverPeriodAggregate
{
    public function __construct(
        public int $driverId,
        public int $rotations,
        public float $clientTonnage,
        public float $providerTonnage,
        public float $gapTonnage,
    ) {}
}
