<?php

namespace App\Domain\Operations\ReadModels\Data;

/** Immutable totals over a period. Raw sums only. */
final readonly class PeriodTotals
{
    public function __construct(
        public int $trips,
        public float $providerTonnage,
        public float $clientTonnage,
        public float $gapTonnage,
    ) {}
}
