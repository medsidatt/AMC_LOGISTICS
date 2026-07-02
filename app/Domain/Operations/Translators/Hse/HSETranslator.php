<?php

namespace App\Domain\Operations\Translators\Hse;

use App\Domain\Operations\Translators\Contracts\DashboardTranslatorInterface;
use App\Domain\Operations\Translators\Presentation\ConclusionArranger;
use App\Domain\Operations\Translators\Presentation\SeverityTally;

/**
 * HSE command center translator. Turns the HSE-routed conclusions into a compliance status
 * (all conclusions, aggregated) and inspection warnings (the forward-looking, non-immediate
 * items).
 *
 * Aggregate / order only — no calculation, no scoring, no DB, no config.
 */
final class HSETranslator implements DashboardTranslatorInterface
{
    public function translate(iterable $conclusions): HSEView
    {
        $list = ConclusionArranger::toList($conclusions);

        return new HSEView(
            new ComplianceStatus(SeverityTally::of($list)),
            new InspectionWarnings(ConclusionArranger::cards(ConclusionArranger::nonImmediate($list))),
        );
    }
}
