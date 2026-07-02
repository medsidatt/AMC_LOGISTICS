<?php

namespace App\Domain\Operations\CommandCenters\Contracts;

use App\Domain\Operations\CommandCenters\Executive\ExecutiveDashboardResponse;

/**
 * The Executive Command Center — the orchestration layer between Operational Intelligence,
 * the Executive Translator, and the HTTP boundary (frozen architecture: Command Centers).
 *
 * It ONLY orchestrates and composes: source facts → conclude → translate → wrap in a
 * presentation-ready response. It contains ZERO business logic — it never calculates KPIs,
 * derives events, instantiates calculators or read models, queries models, reads the
 * database / config / env, filters business data, ranks priorities, or builds charts.
 */
interface ExecutiveCommandCenterInterface
{
    public function dashboard(): ExecutiveDashboardResponse;
}
