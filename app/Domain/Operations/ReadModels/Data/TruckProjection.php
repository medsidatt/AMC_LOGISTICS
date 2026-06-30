<?php

namespace App\Domain\Operations\ReadModels\Data;

/**
 * Immutable projection of one truck's fleet-relevant attributes.
 *
 * Raw values only — capacity/target are the per-truck columns as stored (nullable);
 * resolving the global default belongs to a Domain Calculator, not the Read Model.
 */
final readonly class TruckProjection
{
    public function __construct(
        public int $id,
        public string $matricule,
        public ?float $capacityTonnage,
        public ?int $targetRotationsPerWeek,
        public bool $isAvailable,
        public ?float $availabilityFactor,
        public ?float $maintenanceFactor,
        public ?int $transporterId,
    ) {}
}
