<?php

namespace App\Domain\Operations\Translators\Finance;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The finance revenue risks — the immediate (critical/high) conclusions as cards, ordered by
 * urgency: revenue blocked or at risk right now. Exception-first VIEW; the immediacy comes
 * from each conclusion's own priority policy, not a new rule.
 *
 * @phpstan-consistent-constructor
 */
final readonly class RevenueRisks
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
