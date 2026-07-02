<?php

namespace App\Domain\Operations\Translators\Maintenance;

use App\Domain\Operations\Translators\Presentation\PresentationCard;

/**
 * The maintenance warnings — the forward-looking (non-immediate) conclusions as cards,
 * ordered by urgency: the workshop's early view (e.g. maintenance due soon) before anything
 * becomes critical. Selection uses each conclusion's own immediacy flag; no new rule.
 *
 * @phpstan-consistent-constructor
 */
final readonly class MaintenanceWarnings
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
