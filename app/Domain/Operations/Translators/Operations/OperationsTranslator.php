<?php

namespace App\Domain\Operations\Translators\Operations;

use App\Domain\Operations\Translators\Contracts\DashboardTranslatorInterface;
use App\Domain\Operations\Translators\Presentation\ConclusionArranger;

/**
 * Operations command center translator. Turns the operations-routed conclusions into work
 * queues (grouped by KPI), a problem list (immediate exceptions), and an action list (every
 * conclusion as an actionable card).
 *
 * Group / order only — no calculation, no scoring, no DB, no config.
 */
final class OperationsTranslator implements DashboardTranslatorInterface
{
    public function translate(iterable $conclusions): OperationsView
    {
        $list = ConclusionArranger::toList($conclusions);

        return new OperationsView(
            new OperationalQueues(ConclusionArranger::queuesByKpi($list)),
            new OperationalProblems(ConclusionArranger::cards(ConclusionArranger::immediate($list))),
            new OperationalActions(ConclusionArranger::cards($list)),
        );
    }
}
