<?php

namespace App\Domain\Operations\Translators\Dispatch;

use App\Domain\Operations\Translators\Presentation\PresentationQueue;

/**
 * The dispatch work queues — one queue per KPI (e.g. not-started planned loads), cards
 * ordered by urgency. All-covering product of the dispatch view: every conclusion lands in
 * exactly one queue. Grouping only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class DispatchQueues
{
    /**
     * @param  list<PresentationQueue>  $queues
     */
    public function __construct(
        private array $queues,
    ) {}

    /** @return list<PresentationQueue> */
    public function queues(): array
    {
        return $this->queues;
    }

    public function count(): int
    {
        return count($this->queues);
    }

    public function cardCount(): int
    {
        return array_sum(array_map(static fn (PresentationQueue $q): int => $q->count(), $this->queues));
    }
}
