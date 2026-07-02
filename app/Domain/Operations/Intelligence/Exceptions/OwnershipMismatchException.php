<?php

namespace App\Domain\Operations\Intelligence\Exceptions;

use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\KPI\Enums\KpiId;
use DomainException;

/**
 * Raised when a Business Event and the KPI that emits it disagree on the accountable
 * owner. Operational Intelligence never silently picks one — a mismatch is a catalog
 * authoring error and fails fast (ADR-004: one owner per fact).
 */
final class OwnershipMismatchException extends DomainException
{
    public static function between(EventId $event, string $eventOwner, KpiId $kpi, string $kpiOwner): self
    {
        return new self(sprintf(
            'Ownership mismatch: event [%s] is owned by [%s] but its KPI [%s] is owned by [%s]. A fact must have one owner.',
            $event->value,
            $eventOwner,
            $kpi->value,
            $kpiOwner,
        ));
    }
}
