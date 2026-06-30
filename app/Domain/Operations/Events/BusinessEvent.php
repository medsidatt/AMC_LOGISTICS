<?php

namespace App\Domain\Operations\Events;

use App\Domain\Operations\Contracts\BusinessEventInterface;
use DateTimeImmutable;

/**
 * Immutable base for every business event. Holds only the per-instance facts; each
 * concrete event declares its fixed identity, owner, severity, impact and action.
 *
 * readonly → no setters, no mutation. No Eloquent, SQL, config, services, or bus.
 */
abstract readonly class BusinessEvent implements BusinessEventInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        protected DateTimeImmutable $occurredAt,
        protected int|string|null $entityId,
        protected string $entityType,
        protected array $payload = [],
    ) {}

    abstract public function id(): EventId;

    abstract public function owner(): BusinessOwner;

    abstract public function severity(): BusinessEventSeverity;

    abstract public function businessImpact(): BusinessImpact;

    abstract public function requiredAction(): string;

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function entityId(): int|string|null
    {
        return $this->entityId;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id()->value,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'owner' => $this->owner()->value,
            'severity' => $this->severity()->value,
            'business_impact' => $this->businessImpact()->value,
            'required_action' => $this->requiredAction(),
            'entity_id' => $this->entityId,
            'entity_type' => $this->entityType,
            'payload' => $this->payload,
        ];
    }
}
