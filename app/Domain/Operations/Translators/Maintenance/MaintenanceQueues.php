<?php

namespace App\Domain\Operations\Translators\Maintenance;

use App\Domain\Operations\Translators\Presentation\PresentationQueue;

/**
 * The maintenance work queues — one queue per KPI (breakdown risk, maintenance due), cards
 * ordered by urgency. All-covering product of the maintenance view: every conclusion lands in
 * exactly one queue. Grouping only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class MaintenanceQueues
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
