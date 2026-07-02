<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns billing-readiness / blocked-revenue arithmetic. Pure — the caller supplies the
 * already-resolved tonnages and rate (from the TransportTracking Read Model and the
 * parameter store). No Eloquent, SQL, config, env, or app().
 *
 * No consumer migrated in this increment: billing readiness / blocked revenue
 * (FIN-100/101) are new outcomes wired when their KPIs are built. This is the owner.
 */
interface BillingCalculatorInterface
{
    /** Share of delivered tonnage that is invoice-ready; 0 when nothing delivered. */
    public function readinessRate(float $readyTonnage, float $totalTonnage): float;

    /** Monetary value of tonnage delivered but not yet billable. */
    public function blockedRevenue(float $unbillableTonnage, float $ratePerTonne): float;
}
