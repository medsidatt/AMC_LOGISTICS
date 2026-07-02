<?php

namespace App\Domain\Operations\ReadModels\Data;

/**
 * Immutable projection of one calendar month's RAW Fleeti telemetry aggregates (recorded days,
 * kilometres, consumed litres, refill volume). Stored sums only; no ratio, no comparison, no
 * trend verdict.
 */
final readonly class MonthlyConsumptionPoint
{
    public function __construct(
        public string $month,          // 'YYYY-MM'
        public int $recordedDays,
        public float $kilometers,
        public float $consumedLitres,
        public float $refillsVolume,
    ) {}
}
