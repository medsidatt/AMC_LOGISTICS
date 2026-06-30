<?php

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\Events\BusinessEventSeverity;
use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\BusinessOwner;
use App\Domain\Operations\Events\EventId;
use DateTimeImmutable;

/**
 * A business event is an immutable operational FACT. It carries no calculation and
 * never touches the database, configuration, services, or the event bus. The only
 * language exchanged between Calculators and Operational Intelligence.
 */
interface BusinessEventInterface
{
    public function id(): EventId;

    public function occurredAt(): DateTimeImmutable;

    public function owner(): BusinessOwner;

    public function severity(): BusinessEventSeverity;

    public function businessImpact(): BusinessImpact;

    public function requiredAction(): string;

    public function entityId(): int|string|null;

    public function entityType(): string;

    /** @return array<string, mixed> */
    public function payload(): array;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
