<?php

namespace App\Domain\Operations\ReadModels\Data;

use DateTimeImmutable;

/**
 * Immutable projection of one transport load's weighing facts.
 *
 * Raw per-load values only — the provider and client net weights as stored (nullable).
 * Deciding whether the two differ beyond tolerance is a Domain Calculator's job
 * (WeightCalculator::isGapViolation), never the Read Model's.
 */
final readonly class LoadProjection
{
    public function __construct(
        public int $loadId,
        public ?string $reference,
        public ?int $truckId,
        public ?int $driverId,
        public ?float $providerNetWeight,
        public ?float $clientNetWeight,
        public ?DateTimeImmutable $clientDate,
    ) {}
}
