<?php

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\ReadModels\Data\TruckInspectionProjection;
use Illuminate\Support\Collection;

/**
 * Business projections over inspection recency (per active truck).
 * Returns immutable DTOs of raw values; never applies the SLA rule (an inspection's
 * validity/expiry is a Domain Calculator's job — InspectionCalculator).
 * The only component that queries `inspection_checklists` for this concern.
 */
interface InspectionReadModelInterface
{
    /**
     * The most recent inspection date for each active truck (null when never inspected).
     *
     * @return Collection<int, TruckInspectionProjection>
     */
    public function lastInspectionByActiveTruck(): Collection;
}
