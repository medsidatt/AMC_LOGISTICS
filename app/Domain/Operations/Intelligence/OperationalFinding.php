<?php

namespace App\Domain\Operations\Intelligence;

use App\Domain\Operations\Events\BusinessEventSeverity;
use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\KPI\Enums\KpiId;
use App\Domain\Operations\KPI\Enums\KpiOwner;

/**
 * The diagnosis of a conclusion — "what was observed and why it matters". Binds an
 * operational fact (Business Event) to the one KPI it informs and to the single owner
 * accountable for it (ADR-004). Carries the severity and business impact verbatim from
 * the event, plus the catalog's business question. No calculation, no DB, no UI.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationalFinding
{
    public function __construct(
        private KpiId $kpi,
        private EventId $event,
        private KpiOwner $owner,
        private BusinessEventSeverity $severity,
        private BusinessImpact $businessImpact,
        private string $businessQuestion,
        private OperationalEvidence $evidence,
    ) {}

    public function kpi(): KpiId
    {
        return $this->kpi;
    }

    public function event(): EventId
    {
        return $this->event;
    }

    /** The single department accountable for acting on this finding. */
    public function owner(): KpiOwner
    {
        return $this->owner;
    }

    public function severity(): BusinessEventSeverity
    {
        return $this->severity;
    }

    public function businessImpact(): BusinessImpact
    {
        return $this->businessImpact;
    }

    /** The catalog question this finding answers (catalog "Business question"). */
    public function businessQuestion(): string
    {
        return $this->businessQuestion;
    }

    public function evidence(): OperationalEvidence
    {
        return $this->evidence;
    }
}
