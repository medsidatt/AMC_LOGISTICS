<?php

namespace App\Domain\Operations\ReadModels\Data;

use DateTimeImmutable;

/**
 * Immutable projection of one truck's RAW Fleeti telemetry aggregates over a period.
 *
 * Stored sums/counts only (recorded days, kilometres, consumed litres, refills). It carries NO
 * derived value (no L/100km, no efficiency verdict): ratios and their interpretation belong to a
 * Domain Calculator, never the Read Model — especially since per-day division is unreliable on
 * near-zero-km days (certified data audit note).
 */
final readonly class TruckConsumptionProjection
{
    public function __construct(
        public int $truckId,
        public string $matricule,
        public int $recordedDays,
        public float $kilometers,
        public float $consumedLitres,
        public int $refillsCount,
        public float $refillsVolume,
        public ?DateTimeImmutable $lastRecordDate,
    ) {}
}
