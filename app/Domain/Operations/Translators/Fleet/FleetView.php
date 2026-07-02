<?php

namespace App\Domain\Operations\Translators\Fleet;

use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\Translators\Contracts\DashboardView;

/**
 * The Fleet command center's presentation model — health, capacity, maintenance.
 * Immutable container; holds no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class FleetView implements DashboardView
{
    public function __construct(
        private FleetHealth $health,
        private FleetCapacity $capacity,
        private FleetMaintenance $maintenance,
    ) {}

    public function health(): FleetHealth
    {
        return $this->health;
    }

    public function capacity(): FleetCapacity
    {
        return $this->capacity;
    }

    public function maintenance(): FleetMaintenance
    {
        return $this->maintenance;
    }

    public function commandCenter(): CommandCenter
    {
        return CommandCenter::FLEET;
    }

    public function total(): int
    {
        return $this->health->total();
    }
}
