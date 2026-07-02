<?php

namespace App\Domain\Operations\Translators\Dispatch;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The dispatch action list — every dispatch conclusion as an actionable card (reassign, call
 * the driver, …), ordered by urgency. Drops none. Ordering and formatting only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class DispatchActions
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
