<?php

namespace App\Domain\Operations\Translators\Operations;

use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\Translators\Contracts\DashboardView;

/**
 * The Operations command center's presentation model — queues, problems, actions.
 * Immutable container; holds no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationsView implements DashboardView
{
    public function __construct(
        private OperationalQueues $queues,
        private OperationalProblems $problems,
        private OperationalActions $actions,
    ) {}

    public function queues(): OperationalQueues
    {
        return $this->queues;
    }

    public function problems(): OperationalProblems
    {
        return $this->problems;
    }

    public function actions(): OperationalActions
    {
        return $this->actions;
    }

    public function commandCenter(): CommandCenter
    {
        return CommandCenter::OPERATIONS;
    }

    public function total(): int
    {
        return $this->actions->count();
    }
}
