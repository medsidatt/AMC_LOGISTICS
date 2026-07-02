<?php

namespace App\Domain\Operations\Translators\Finance;

use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\Translators\Contracts\DashboardView;

/**
 * The Finance command center's presentation model — billing queues and revenue risks.
 * Immutable container; holds no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class FinanceView implements DashboardView
{
    public function __construct(
        private BillingQueues $billing,
        private RevenueRisks $revenueRisks,
    ) {}

    public function billing(): BillingQueues
    {
        return $this->billing;
    }

    public function revenueRisks(): RevenueRisks
    {
        return $this->revenueRisks;
    }

    public function commandCenter(): CommandCenter
    {
        return CommandCenter::FINANCE;
    }

    public function total(): int
    {
        return $this->billing->cardCount();
    }
}
