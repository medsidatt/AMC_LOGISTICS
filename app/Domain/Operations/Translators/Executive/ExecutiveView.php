<?php

namespace App\Domain\Operations\Translators\Executive;

use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\Translators\Contracts\DashboardView;

/**
 * The Executive command center's presentation model — summary, alerts, priorities.
 * Immutable container of already-built value objects; holds no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ExecutiveView implements DashboardView
{
    public function __construct(
        private ExecutiveSummary $summary,
        private ExecutiveAlerts $alerts,
        private ExecutivePriorities $priorities,
    ) {}

    public function summary(): ExecutiveSummary
    {
        return $this->summary;
    }

    public function alerts(): ExecutiveAlerts
    {
        return $this->alerts;
    }

    public function priorities(): ExecutivePriorities
    {
        return $this->priorities;
    }

    public function commandCenter(): CommandCenter
    {
        return CommandCenter::EXECUTIVE;
    }

    public function total(): int
    {
        return $this->priorities->count();
    }
}
