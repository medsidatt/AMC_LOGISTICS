<?php

namespace App\Domain\Operations\Translators\Operations;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The operations problem list — the immediate (critical/high) conclusions as cards, ordered
 * by urgency. Exception-first VIEW of already-existing conclusions; the immediacy comes from
 * each conclusion's own priority policy, not a new rule.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationalProblems
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
