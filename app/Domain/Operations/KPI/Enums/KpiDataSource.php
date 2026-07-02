<?php

namespace App\Domain\Operations\KPI\Enums;

use App\Domain\Operations\Contracts\FleetReadModelInterface;
use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;

/**
 * The Read Model that supplies a KPI's data (docs/kpi-catalog.md "Data sources").
 * Read Models are the ONLY database readers (ADR-005); the Registry references them
 * by identity, never instantiates or queries them.
 *
 * The architecture's full Read Model set is six. Two are implemented today
 * (R1.2 — TransportTracking, Fleet); the remaining four are planned and carry no
 * contract yet. `contract()` returns the interface FQN where one exists so the
 * Registry's reference can be verified against a real type.
 */
enum KpiDataSource: string
{
    case TRANSPORT_TRACKING = 'transport_tracking';
    case FLEET = 'fleet';
    case DISPATCH = 'dispatch';
    case MAINTENANCE = 'maintenance';
    case INSPECTION = 'inspection';
    case FUEL = 'fuel';

    /** Read Model contract FQN when implemented, else null (planned in R1.2's remaining set). */
    public function contract(): ?string
    {
        return match ($this) {
            self::TRANSPORT_TRACKING => TransportTrackingReadModelInterface::class,
            self::FLEET => FleetReadModelInterface::class,
            self::DISPATCH, self::MAINTENANCE, self::INSPECTION, self::FUEL => null,
        };
    }

    /** Whether a concrete Read Model contract backs this source today. */
    public function isImplemented(): bool
    {
        return $this->contract() !== null;
    }
}
