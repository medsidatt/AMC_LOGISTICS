<?php

namespace App\Domain\Operations\Translators\Fleet;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The fleet maintenance concerns — the maintenance-owned conclusions displayed on the fleet
 * command center (e.g. trucks at breakdown risk) as cards, ordered by urgency. A partition by
 * the owner the conclusion already names; no calculation.
 *
 * @phpstan-consistent-constructor
 */
final readonly class FleetMaintenance
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
