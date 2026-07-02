<?php

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\ReadModels\Data\DispatchProjection;
use App\Domain\Operations\ReadModels\Data\ExpectedTicketProjection;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Business projections over the daily dispatch aggregate (dispatches + their dispatch-born
 * expected tickets). Returns immutable DTOs of raw facts (driver/truck/live status, ticket
 * status/timestamps); never classifies planned/started/completed and never counts rates
 * (DispatchCalculator owns that arithmetic). The only component that reads `daily_dispatches`
 * and its child `expected_transport_tickets` for this concern.
 */
interface DispatchReadModelInterface
{
    /**
     * All planned dispatches for a date, with their raw stored live status.
     *
     * @return Collection<int, DispatchProjection>
     */
    public function program(CarbonInterface $date): Collection;

    /**
     * Tickets flagged missing — a delivered/expected load with no registered transport
     * ticket by its deadline (dispatch-born children of the Dispatch aggregate).
     *
     * @return Collection<int, ExpectedTicketProjection>
     */
    public function missingTickets(): Collection;
}
