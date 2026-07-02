<?php

namespace App\Domain\Operations\Intelligence;

/**
 * The prescription of a conclusion — "what should happen?". Every string here is
 * catalog metadata copied verbatim from the KPI Registry (decision · required action ·
 * drill-down destination). Intelligence never invents an action; it surfaces the one
 * the KPI Catalog already mandates. No calculation, no presentation, no UI.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationalRecommendation
{
    public function __construct(
        private string $decision,
        private string $requiredAction,
        private string $drillDownTarget,
    ) {}

    /** The business decision the KPI exists to support (catalog "Business decision"). */
    public function decision(): string
    {
        return $this->decision;
    }

    /** Exactly what to do (catalog "Required action"). */
    public function requiredAction(): string
    {
        return $this->requiredAction;
    }

    /** Where the owner goes next to act (catalog "Drill-down"). */
    public function drillDownTarget(): string
    {
        return $this->drillDownTarget;
    }
}
