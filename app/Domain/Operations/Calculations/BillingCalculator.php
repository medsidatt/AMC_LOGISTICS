<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\BillingCalculatorInterface;

/**
 * Billing-readiness / blocked-revenue arithmetic. Pure over supplied tonnages/rate.
 * No Eloquent, SQL, config, env, or app().
 */
class BillingCalculator implements BillingCalculatorInterface
{
    public function readinessRate(float $readyTonnage, float $totalTonnage): float
    {
        return $totalTonnage > 0 ? $readyTonnage / $totalTonnage : 0.0;
    }

    public function blockedRevenue(float $unbillableTonnage, float $ratePerTonne): float
    {
        return $unbillableTonnage * $ratePerTonne;
    }
}
