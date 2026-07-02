<?php

namespace App\Domain\Operations\Intelligence;

use App\Domain\Operations\Events\BusinessEventSeverity;
use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\KPI\Enums\KpiId;
use App\Domain\Operations\KPI\Enums\KpiOwner;
use DateTimeImmutable;

/**
 * One operational conclusion — the decision engine's output. It is a business
 * statement, not a calculation: it says what requires attention, why, who owns it,
 * what to do, and how urgent it is. It carries NO formulas and NO percentages it did
 * not receive already computed from the event.
 *
 * Composed of a finding (diagnosis), a recommendation (prescription), a priority
 * (urgency), an explanation (the sentence), and a stable id. final + readonly →
 * built once by the engine, never mutated. No Eloquent, SQL, config, env, or UI.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationalConclusion
{
    public function __construct(
        private string $id,
        private OperationalFinding $finding,
        private OperationalRecommendation $recommendation,
        private OperationalPriority $priority,
        private string $explanation,
        private DateTimeImmutable $occurredAt,
    ) {}

    // ── Composed parts ──────────────────────────────────────────────────────────

    public function finding(): OperationalFinding
    {
        return $this->finding;
    }

    public function recommendation(): OperationalRecommendation
    {
        return $this->recommendation;
    }

    public function priority(): OperationalPriority
    {
        return $this->priority;
    }

    public function evidence(): OperationalEvidence
    {
        return $this->finding->evidence();
    }

    // ── The required conclusion fields (flat accessors) ─────────────────────────

    /** (1) Stable, deterministic identity of this conclusion. */
    public function id(): string
    {
        return $this->id;
    }

    /** (2) The Business Event that triggered this conclusion. */
    public function event(): EventId
    {
        return $this->finding->event();
    }

    /** (3) The single KPI this conclusion relates to. */
    public function kpi(): KpiId
    {
        return $this->finding->kpi();
    }

    /** (4) How serious the underlying fact is. */
    public function severity(): BusinessEventSeverity
    {
        return $this->finding->severity();
    }

    /** (5) What kind of harm results if ignored. */
    public function businessImpact(): BusinessImpact
    {
        return $this->finding->businessImpact();
    }

    /** (6) The one accountable department. */
    public function owner(): KpiOwner
    {
        return $this->finding->owner();
    }

    /** (7) The business decision this supports. */
    public function decision(): string
    {
        return $this->recommendation->decision();
    }

    /** (8) Exactly what should happen. */
    public function requiredAction(): string
    {
        return $this->recommendation->requiredAction();
    }

    /** (9) The plain-language reason — "why". */
    public function explanation(): string
    {
        return $this->explanation;
    }

    /** (10) The entity the conclusion concerns. */
    public function affectedEntityType(): string
    {
        return $this->finding->evidence()->entityType();
    }

    public function affectedEntityId(): int|string|null
    {
        return $this->finding->evidence()->entityId();
    }

    /** (11) Where the owner goes next to act. */
    public function drillDownTarget(): string
    {
        return $this->recommendation->drillDownTarget();
    }

    /** (12) When the underlying fact occurred. */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** (13) The supporting evidence facts. @return array<string, mixed> */
    public function evidenceFacts(): array
    {
        return $this->finding->evidence()->facts();
    }

    /** (14) The urgency rank (1 = most urgent). */
    public function priorityRank(): int
    {
        return $this->priority->rank();
    }
}
