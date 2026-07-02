<?php

namespace App\Domain\Operations\Translators\Fleet;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The fleet capacity concerns — the fleet-owned conclusions (capacity today / at risk /
 * utilization / truck productivity) as cards, ordered by urgency. A partition by the owner
 * the conclusion already names; no calculation.
 *
 * @phpstan-consistent-constructor
 */
final readonly class FleetCapacity
{
    /**
     * @param  list<PresentationCard>  $cards
     */
    public function __construct(
        private array $cards,
    ) {}

    /** @return list<PresentationCard> */
    public function cards(): array
    {
        return $this->cards;
    }

    public function count(): int
    {
        return count($this->cards);
    }
}
