<?php

namespace App\Domain\Operations\ReadModels\Data;

/**
 * Immutable tonnage for one fiscal month (label normalized by the injected
 * fiscal start day). Raw sums only — no objective comparison here.
 */
final readonly class MonthlyTonnage
{
    public function __construct(
        public string $month, // 'Y-m'
        public float $providerTonnage,
        public float $clientTonnage,
        public float $gapTonnage,
        public int $trips,
    ) {}
}
