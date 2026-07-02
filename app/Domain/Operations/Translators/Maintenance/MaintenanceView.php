<?php

namespace App\Domain\Operations\Translators\Maintenance;

use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\Translators\Contracts\DashboardView;

/**
 * The Maintenance command center's presentation model — queues and warnings.
 * Immutable container; holds no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class MaintenanceView implements DashboardView
{
    public function __construct(
        private MaintenanceQueues $queues,
        private MaintenanceWarnings $warnings,
    ) {}

    public function queues(): MaintenanceQueues
    {
        return $this->queues;
    }

    public function warnings(): MaintenanceWarnings
    {
        return $this->warnings;
    }

    public function commandCenter(): CommandCenter
    {
        return CommandCenter::MAINTENANCE;
    }

    public function total(): int
    {
        return $this->queues->cardCount();
    }
}
