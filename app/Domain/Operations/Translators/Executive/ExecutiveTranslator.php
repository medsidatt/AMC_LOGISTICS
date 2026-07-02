<?php

namespace App\Domain\Operations\Translators\Executive;

use App\Domain\Operations\Translators\Contracts\DashboardTranslatorInterface;
use App\Domain\Operations\Translators\Presentation\ConclusionArranger;
use App\Domain\Operations\Translators\Presentation\SeverityTally;

/**
 * Executive command center translator. The executive consumes conclusions from every
 * owner, so its caller routes the full conclusion set here. It produces a summary (what is
 * the overall picture), alerts (what needs action now), and priorities (everything, most
 * urgent first).
 *
 * Group / order / aggregate only — no calculation, no scoring, no DB, no config.
 */
final class ExecutiveTranslator implements DashboardTranslatorInterface
{
    public function translate(iterable $conclusions): ExecutiveView
    {
        $list = ConclusionArranger::toList($conclusions);

        return new ExecutiveView(
            new ExecutiveSummary(SeverityTally::of($list)),
            new ExecutiveAlerts(ConclusionArranger::cards(ConclusionArranger::immediate($list))),
            new ExecutivePriorities(ConclusionArranger::cards($list)),
        );
    }
}
