<?php

namespace App\Domain\Operations\ReadModels\Data;

use DateTimeImmutable;

/**
 * Immutable projection of one expected transport ticket (under-ticketing reconciliation).
 *
 * Raw values only — the ticket's stored status and timestamps. Whether a missing ticket
 * warrants an operational event is the deriver's concern; the Read Model only surfaces the
 * facts recorded on the row.
 */
final readonly class ExpectedTicketProjection
{
    public function __construct(
        public int $ticketId,
        public ?int $truckId,
        public ?int $providerId,
        public ?int $dailyDispatchId,
        public string $status,
        public ?DateTimeImmutable $loadedAt,
        public ?DateTimeImmutable $deadlineAt,
    ) {}
}
