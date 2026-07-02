<?php

namespace App\Domain\Operations\Translators\Fleet;

use App\Domain\Operations\KPI\Enums\KpiOwner;
use App\Domain\Operations\Translators\Contracts\DashboardTranslatorInterface;
use App\Domain\Operations\Translators\Presentation\ConclusionArranger;
use App\Domain\Operations\Translators\Presentation\SeverityTally;

/**
 * Fleet command center translator. The fleet board shows its own capacity conclusions
 * alongside the maintenance conclusions routed to it (a maintenance-owned KPI may also be
 * displayed on fleet). It produces a health snapshot (all conclusions, aggregated), the
 * capacity concerns (fleet-owned), and the maintenance concerns (maintenance-owned).
 *
 * Partition by the owner each conclusion already names, aggregate, order — no calculation,
 * no scoring, no DB, no config.
 */
final class FleetTranslator implements DashboardTranslatorInterface
{
    public function translate(iterable $conclusions): FleetView
    {
        $list = ConclusionArranger::toList($conclusions);

        return new FleetView(
            new FleetHealth(SeverityTally::of($list)),
            new FleetCapacity(ConclusionArranger::cards(ConclusionArranger::withOwner($list, KpiOwner::FLEET))),
            new FleetMaintenance(ConclusionArranger::cards(ConclusionArranger::withOwner($list, KpiOwner::MAINTENANCE))),
        );
    }
}
