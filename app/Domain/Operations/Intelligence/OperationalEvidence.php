<?php

namespace App\Domain\Operations\Intelligence;

use App\Domain\Operations\Events\EventId;
use DateTimeImmutable;

/**
 * The proof behind a conclusion — the operational FACT carried by a Business Event,
 * never recomputed here. Holds the event identity, the affected entity, when it
 * occurred, and the raw pre-computed payload (e.g. ['count' => 14, 'subject' =>
 * 'incomplete transport tickets']). Intelligence READS these values; it never
 * derives them. No Eloquent, SQL, config, env, or calculation.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationalEvidence
{
    /**
     * @param  array<string, mixed>  $facts  the event payload — already computed upstream
     */
    public function __construct(
        private EventId $event,
        private string $entityType,
        private int|string|null $entityId,
        private DateTimeImmutable $occurredAt,
        private array $facts,
    ) {}

    public function event(): EventId
    {
        return $this->event;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): int|string|null
    {
        return $this->entityId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function facts(): array
    {
        return $this->facts;
    }

    /**
     * A human-readable phrase describing the evidence, built ONLY from values already
     * present on the event (no arithmetic). Prefers an explicit summary, then a
     * count+subject pair, and finally falls back to the affected entity.
     */
    public function summary(): string
    {
        if (isset($this->facts['summary']) && is_string($this->facts['summary'])) {
            return $this->facts['summary'];
        }

        if (isset($this->facts['count'], $this->facts['subject'])) {
            return trim($this->facts['count'].' '.$this->facts['subject']);
        }

        return $this->entityId === null
            ? $this->entityType
            : $this->entityType.' '.$this->entityId;
    }
}
