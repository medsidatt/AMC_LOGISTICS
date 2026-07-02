<?php

namespace App\Domain\Fuel\ValueObjects;

use App\Enums\Fuel\KpiEligibility;
use App\Enums\Fuel\PersistenceDecision;
use App\Enums\Fuel\ReviewDecision;

/**
 * The three decisions ClassificationPolicy produces from a FuelTransactionClassification.
 * Immutable; the only carrier of a policy outcome across the codebase.
 */
final class PolicyOutcome
{
    public function __construct(
        public readonly PersistenceDecision $persistence,
        public readonly KpiEligibility $kpiEligibility,
        public readonly ReviewDecision $review,
    ) {}

    public function isAccepted(): bool
    {
        return $this->persistence === PersistenceDecision::ACCEPT;
    }

    public function isKpiEligible(): bool
    {
        return $this->kpiEligibility === KpiEligibility::ELIGIBLE;
    }

    public function needsReview(): bool
    {
        return $this->review === ReviewDecision::REQUIRED;
    }

    /** @return array{persistence:string, kpi:string, review:string} */
    public function toArray(): array
    {
        return [
            'persistence' => $this->persistence->value,
            'kpi' => $this->kpiEligibility->value,
            'review' => $this->review->value,
        ];
    }
}
