<?php

namespace App\Domain\Operations\Translators\Operations;

use App\Domain\Operations\Translators\Presentation\PresentationQueue;

/**
 * The operations work queues — one queue per KPI, each holding its conclusion cards ordered
 * by urgency. This is the all-covering product of the operations view: every conclusion
 * lands in exactly one queue, so none is dropped or duplicated. Grouping only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationalQueues
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

    /** Total cards across all queues (equals the conclusions routed in). */
    public function cardCount(): int
    {
        return array_sum(array_map(static fn (PresentationQueue $q): int => $q->count(), $this->queues));
    }
}
