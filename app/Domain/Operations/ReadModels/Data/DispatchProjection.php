<?php

namespace App\Domain\Operations\ReadModels\Data;

use DateTimeImmutable;

/**
 * Immutable projection of one planned daily dispatch.
 *
 * Raw values only — the driver/truck and the stored live status (nullable: null until
 * telemetry has produced a movement status). Classifying "not started" vs "started" vs
 * "completed" from that status is the consumer's concern, not the Read Model's.
 */
final readonly class DispatchProjection
{
    public function __construct(
        public int $dispatchId,
        public ?int $truckId,
        public ?int $driverId,
        public DateTimeImmutable $dispatchDate,
        public ?string $currentStatus,
    ) {}
}
