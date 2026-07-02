<?php

namespace App\Domain\Operations\Translators\Dispatch;

use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\Translators\Contracts\DashboardView;

/**
 * The Dispatch command center's presentation model — queues and actions.
 * Immutable container; holds no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class DispatchView implements DashboardView
{
    public function __construct(
        private DispatchQueues $queues,
        private DispatchActions $actions,
    ) {}

    public function queues(): DispatchQueues
    {
        return $this->queues;
    }

    public function actions(): DispatchActions
    {
        return $this->actions;
    }

    public function commandCenter(): CommandCenter
    {
        return CommandCenter::DISPATCH;
    }

    public function total(): int
    {
        return $this->actions->count();
    }
}
