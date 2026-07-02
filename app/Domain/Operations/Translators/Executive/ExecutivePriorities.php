<?php

namespace App\Domain\Operations\Translators\Executive;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The executive priority timeline — EVERY conclusion as a card, ordered by urgency (most
 * urgent first). This is the all-covering product of the executive view: it drops no
 * conclusion. Ordering reads the priority rank each conclusion already carries.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ExecutivePriorities
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
