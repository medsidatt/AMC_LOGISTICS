<?php

namespace App\Domain\Operations\ReadModels;

use App\Domain\Operations\Contracts\DispatchReadModelInterface;
use App\Domain\Operations\ReadModels\Data\DispatchProjection;
use App\Domain\Operations\ReadModels\Data\ExpectedTicketProjection;
use App\Models\DailyDispatch;
use App\Models\ExpectedTransportTicket;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only projections over `daily_dispatches`.
 *
 * Normalizes the dispatch program the dispatch board re-queries today
 * (`DailyDispatch::onDate(...)`) and the missing-ticket worklist the reconciliation screen
 * re-queries (`ExpectedTransportTicket::status(MISSING)`). `expected_transport_tickets` are
 * dispatch-born children (mandatory `daily_dispatch_id`), so the Dispatch aggregate owns
 * them. Exposes RAW facts only — classifying "not started" and counting rates belong to the
 * consumer and the DispatchCalculator. No calculation, no parameter, no event.
 */
class DispatchReadModel implements DispatchReadModelInterface
{
    public function program(CarbonInterface $date): Collection
    {
        return DailyDispatch::query()
            ->whereDate('dispatch_date', $date->toDateString())
            ->orderBy('id')
            ->get(['id', 'truck_id', 'driver_id', 'dispatch_date', 'current_status'])
            ->map(fn (DailyDispatch $d): DispatchProjection => new DispatchProjection(
                (int) $d->id,
                $d->truck_id !== null ? (int) $d->truck_id : null,
                $d->driver_id !== null ? (int) $d->driver_id : null,
                new DateTimeImmutable((string) $d->dispatch_date->toDateString()),
                $d->current_status !== null ? (string) $d->current_status : null,
            ))
            ->values();
    }

    public function missingTickets(): Collection
    {
        return ExpectedTransportTicket::query()
            ->where('status', ExpectedTransportTicket::STATUS_MISSING)
            ->orderByDesc('loaded_at')
            ->get(['id', 'truck_id', 'provider_id', 'daily_dispatch_id', 'status', 'loaded_at', 'deadline_at'])
            ->map(fn (ExpectedTransportTicket $t): ExpectedTicketProjection => new ExpectedTicketProjection(
                (int) $t->id,
                $t->truck_id !== null ? (int) $t->truck_id : null,
                $t->provider_id !== null ? (int) $t->provider_id : null,
                $t->daily_dispatch_id !== null ? (int) $t->daily_dispatch_id : null,
                (string) $t->status,
                $t->loaded_at !== null ? new DateTimeImmutable((string) $t->loaded_at->toIso8601String()) : null,
                $t->deadline_at !== null ? new DateTimeImmutable((string) $t->deadline_at->toIso8601String()) : null,
            ))
            ->values();
    }
}
