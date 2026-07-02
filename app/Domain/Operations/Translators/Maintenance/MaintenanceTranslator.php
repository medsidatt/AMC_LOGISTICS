<?php

namespace App\Domain\Operations\Translators\Maintenance;

use App\Domain\Operations\Translators\Contracts\DashboardTranslatorInterface;
use App\Domain\Operations\Translators\Presentation\ConclusionArranger;

/**
 * Maintenance command center translator. Turns the maintenance-routed conclusions into work
 * queues (grouped by KPI) and warnings (the forward-looking, non-immediate items).
 *
 * Group / order only — no calculation, no scoring, no DB, no config.
 */
final class MaintenanceTranslator implements DashboardTranslatorInterface
{
    public function translate(iterable $conclusions): MaintenanceView
    {
        $list = ConclusionArranger::toList($conclusions);

        return new MaintenanceView(
            new MaintenanceQueues(ConclusionArranger::queuesByKpi($list)),
            new MaintenanceWarnings(ConclusionArranger::cards(ConclusionArranger::nonImmediate($list))),
        );
    }
}
