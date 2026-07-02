<?php

namespace App\Domain\Operations\Translators\Operations;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The operations action list — every conclusion as an actionable card (it carries the
 * catalog decision, required action, and drill-down), ordered by urgency. Covers all routed
 * conclusions; drops none. Ordering and formatting only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationalActions
{
    /**
     * @param  list<PresentationCard>  $cards  ordered by urgency
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
