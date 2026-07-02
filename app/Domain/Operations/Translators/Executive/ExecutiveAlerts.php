<?php

namespace App\Domain\Operations\Translators\Executive;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The executive alert list — the immediate (critical/high) conclusions as cards, ordered
 * by urgency. A filtered, ordered VIEW of already-existing conclusions; selection uses the
 * conclusion's own immediacy flag, never a new threshold.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ExecutiveAlerts
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
