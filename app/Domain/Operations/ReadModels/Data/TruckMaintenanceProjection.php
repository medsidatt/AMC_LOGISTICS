<?php

namespace App\Domain\Operations\ReadModels\Data;

use DateTimeImmutable;

/**
 * Immutable projection of one active truck's RAW maintenance fields.
 *
 * Raw stored values only — the truck's odometer, its tracking mode and interval column, and
 * the latest maintenance record's kilometres/date. It carries NO derived value (no "remaining",
 * no rotations-since, no red/overdue): computing distance-to-service and deciding overdue are a
 * Domain Calculator's job, never the Read Model's.
 */
final readonly class TruckMaintenanceProjection
{
    public function __construct(
        public int $truckId,
        public string $matricule,
        public string $maintenanceType,
        public float $totalKilometers,
        public ?float $kmMaintenanceInterval,
        public ?float $lastMaintenanceKm,
        public ?DateTimeImmutable $lastMaintenanceDate,
    ) {}
}
