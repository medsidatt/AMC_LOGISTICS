<?php

namespace App\Domain\Operations\ReadModels\Data;

use DateTimeImmutable;

/**
 * Immutable projection of one active truck's inspection recency.
 *
 * Raw values only — the last inspection date as stored (nullable when a truck has never
 * been inspected). Deciding whether that date is expired against the SLA is a Domain
 * Calculator's job (InspectionCalculator), not the Read Model's.
 */
final readonly class TruckInspectionProjection
{
    public function __construct(
        public int $truckId,
        public string $matricule,
        public ?DateTimeImmutable $lastInspectionDate,
    ) {}
}
