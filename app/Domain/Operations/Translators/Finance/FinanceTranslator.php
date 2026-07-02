<?php

namespace App\Domain\Operations\Translators\Finance;

use App\Domain\Operations\Translators\Contracts\DashboardTranslatorInterface;
use App\Domain\Operations\Translators\Presentation\ConclusionArranger;

/**
 * Finance command center translator. Turns the finance-routed conclusions into billing queues
 * (grouped by KPI) and revenue risks (the immediate exceptions).
 *
 * Group / order only — no calculation, no scoring, no DB, no config.
 */
final class FinanceTranslator implements DashboardTranslatorInterface
{
    public function translate(iterable $conclusions): FinanceView
    {
        $list = ConclusionArranger::toList($conclusions);

        return new FinanceView(
            new BillingQueues(ConclusionArranger::queuesByKpi($list)),
            new RevenueRisks(ConclusionArranger::cards(ConclusionArranger::immediate($list))),
        );
    }
}
