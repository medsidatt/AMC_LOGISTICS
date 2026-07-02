<?php

namespace App\Domain\Operations\Translators\Hse;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The HSE inspection warnings — the forward-looking (non-immediate) compliance conclusions as
 * cards, ordered by urgency: inspections to validate before they expire. Selection uses each
 * conclusion's own immediacy flag; no new rule.
 *
 * @phpstan-consistent-constructor
 */
final readonly class InspectionWarnings
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
