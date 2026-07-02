<?php

namespace App\Domain\Operations\Translators\Dispatch;

use App\Domain\Operations\Translators\Contracts\DashboardTranslatorInterface;
use App\Domain\Operations\Translators\Presentation\ConclusionArranger;

/**
 * Dispatch command center translator. Turns the dispatch-routed conclusions into work
 * queues (grouped by KPI) and an action list (every conclusion as an actionable card).
 *
 * Group / order only — no calculation, no scoring, no DB, no config.
 */
final class DispatchTranslator implements DashboardTranslatorInterface
{
    public function translate(iterable $conclusions): DispatchView
    {
        $list = ConclusionArranger::toList($conclusions);

        return new DispatchView(
            new DispatchQueues(ConclusionArranger::queuesByKpi($list)),
            new DispatchActions(ConclusionArranger::cards($list)),
        );
    }
}
