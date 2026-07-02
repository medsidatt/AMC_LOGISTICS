<?php

namespace App\Domain\Operations\Events\Derivers;

use App\Domain\Operations\Contracts\DispatchCalculatorInterface;
use App\Domain\Operations\Contracts\DispatchReadModelInterface;
use App\Domain\Operations\Events\Derivers\Contracts\BusinessEventDeriver;
use App\Domain\Operations\Events\MissingTransportTicket;
use App\Domain\Operations\Events\TruckUnavailable;
use App\Domain\Operations\ReadModels\Data\DispatchProjection;
use App\Domain\Operations\ReadModels\Data\ExpectedTicketProjection;

/**
 * Derives the Dispatch aggregate's events from the Dispatch Read Model:
 *   - {@see TruckUnavailable}      — a planned dispatch that has not started (calculator call)
 *   - {@see MissingTransportTicket} — a load with no registered ticket (raw status fact)
 *
 * The "not started" classification is the DispatchCalculator's; a missing ticket is a stored
 * status the Read Model already surfaces, so no calculator is needed for it. The deriver
 * computes nothing.
 */
final class DispatchEventDeriver implements BusinessEventDeriver
{
    public function __construct(
        private readonly DispatchReadModelInterface $dispatch,
        private readonly DispatchCalculatorInterface $calculator,
    ) {}

    public function derive(DerivationContext $context): array
    {
        $events = [];

        foreach ($this->dispatch->program($context->asOf) as $dispatch) {
            /** @var DispatchProjection $dispatch */
            if (! $this->calculator->isNotStarted($dispatch->currentStatus)) {
                continue;
            }

            $events[] = new TruckUnavailable(
                $context->asOf,
                $dispatch->truckId ?? $dispatch->dispatchId,
                'truck',
                [
                    'dispatch_id' => $dispatch->dispatchId,
                    'driver_id' => $dispatch->driverId,
                    'dispatch_date' => $dispatch->dispatchDate->format('Y-m-d'),
                ],
            );
        }

        foreach ($this->dispatch->missingTickets() as $ticket) {
            /** @var ExpectedTicketProjection $ticket */
            $events[] = new MissingTransportTicket(
                $ticket->loadedAt ?? $context->asOf,
                $ticket->ticketId,
                'expected_transport_ticket',
                [
                    'truck_id' => $ticket->truckId,
                    'provider_id' => $ticket->providerId,
                    'daily_dispatch_id' => $ticket->dailyDispatchId,
                ],
            );
        }

        return $events;
    }
}
