<?php

namespace App\Domain\Operations\Translators\Presentation;

use App\Domain\Operations\Intelligence\OperationalConclusion;
use DateTimeImmutable;

/**
 * The presentation-neutral rendering of ONE Operational Conclusion. It carries only
 * values that already exist on the conclusion — renamed and grouped for display, never
 * calculated. It is the single card shape every command center reuses, so formatting
 * lives here once (Reuse > Create).
 *
 * No arithmetic, no scoring, no thresholds, no DB, no config, no UI. Building a card is a
 * pure copy from the conclusion; `immediate` reuses the conclusion's own priority policy.
 *
 * @phpstan-consistent-constructor
 */
final readonly class PresentationCard
{
    /**
     * @param  array<string, mixed>  $evidenceFacts  the pre-computed event payload, verbatim
     */
    public function __construct(
        private string $conclusionId,
        private string $kpiCode,
        private string $eventCode,
        private string $businessQuestion,
        private string $headline,
        private string $explanation,
        private string $decision,
        private string $requiredAction,
        private string $drillDownTarget,
        private string $severityLabel,
        private string $impactLabel,
        private string $ownerLabel,
        private int $priorityRank,
        private bool $immediate,
        private string $entityType,
        private int|string|null $entityId,
        private DateTimeImmutable $occurredAt,
        private array $evidenceFacts,
    ) {}

    /**
     * Build a card from a conclusion. Pure mapping — every field is copied from a value
     * the conclusion already exposes. Nothing is derived or computed here.
     */
    public static function fromConclusion(OperationalConclusion $c): self
    {
        return new self(
            $c->id(),
            $c->kpi()->value,
            $c->event()->value,
            $c->finding()->businessQuestion(),
            $c->evidence()->summary(),
            $c->explanation(),
            $c->decision(),
            $c->requiredAction(),
            $c->drillDownTarget(),
            $c->severity()->value,
            $c->businessImpact()->value,
            $c->owner()->value,
            $c->priorityRank(),
            $c->priority()->isImmediate(),
            $c->affectedEntityType(),
            $c->affectedEntityId(),
            $c->occurredAt(),
            $c->evidenceFacts(),
        );
    }

    public function conclusionId(): string
    {
        return $this->conclusionId;
    }

    public function kpiCode(): string
    {
        return $this->kpiCode;
    }

    public function eventCode(): string
    {
        return $this->eventCode;
    }

    /** The human question this card answers (catalog "Business question"). */
    public function businessQuestion(): string
    {
        return $this->businessQuestion;
    }

    /** A short phrase describing the fact (evidence summary). */
    public function headline(): string
    {
        return $this->headline;
    }

    /** The full business sentence (the "why"). */
    public function explanation(): string
    {
        return $this->explanation;
    }

    public function decision(): string
    {
        return $this->decision;
    }

    public function requiredAction(): string
    {
        return $this->requiredAction;
    }

    public function drillDownTarget(): string
    {
        return $this->drillDownTarget;
    }

    public function severityLabel(): string
    {
        return $this->severityLabel;
    }

    public function impactLabel(): string
    {
        return $this->impactLabel;
    }

    public function ownerLabel(): string
    {
        return $this->ownerLabel;
    }

    /** 1 (most urgent) … 5 (least). Copied from the conclusion; not recomputed. */
    public function priorityRank(): int
    {
        return $this->priorityRank;
    }

    /** Critical or high — the conclusion's own urgency policy, reused verbatim. */
    public function isImmediate(): bool
    {
        return $this->immediate;
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
    public function evidenceFacts(): array
    {
        return $this->evidenceFacts;
    }
}
