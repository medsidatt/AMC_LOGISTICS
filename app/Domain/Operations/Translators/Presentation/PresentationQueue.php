<?php

namespace App\Domain\Operations\Translators\Presentation;

/**
 * A named, ordered group of {@see PresentationCard} — one work queue on a command center
 * (e.g. all cards for a single KPI). It is a container only: it groups and labels cards
 * that already exist. No calculation, no scoring, no DB, no UI.
 *
 * @phpstan-consistent-constructor
 */
final readonly class PresentationQueue
{
    /**
     * @param  list<PresentationCard>  $cards  already ordered by the translator
     */
    public function __construct(
        private string $key,
        private string $label,
        private array $cards,
    ) {}

    /** Stable grouping key (e.g. the KPI code). */
    public function key(): string
    {
        return $this->key;
    }

    /** Human label for the queue (e.g. the KPI's business question). */
    public function label(): string
    {
        return $this->label;
    }

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
