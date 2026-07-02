<?php

namespace App\Domain\Operations\ReadModels\Data;

/**
 * Immutable projection of the fuel review queue's STORED state — counts by the persisted
 * review_status plus the oldest pending timestamp. Pure tally of what the ClassificationPolicy
 * and reviewers already wrote; deciding whether the queue is "too old" or "too big" is not
 * this DTO's business.
 */
final readonly class FuelReviewQueueStats
{
    public function __construct(
        public int $pending,
        public int $resolved,
        public int $none,
        public ?string $oldestPendingAt,   // 'Y-m-d H:i:s' | null
    ) {}
}
