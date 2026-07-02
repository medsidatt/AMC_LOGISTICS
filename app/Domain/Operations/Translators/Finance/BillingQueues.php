<?php

namespace App\Domain\Operations\Translators\Finance;

use App\Domain\Operations\Translators\Presentation\PresentationQueue;

/**
 * The finance billing queues — one queue per KPI (billing readiness, missing tickets), cards
 * ordered by urgency. All-covering product of the finance view: every conclusion lands in
 * exactly one queue. Grouping only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class BillingQueues
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
