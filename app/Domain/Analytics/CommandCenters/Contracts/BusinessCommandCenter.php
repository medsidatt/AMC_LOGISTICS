<?php

namespace App\Domain\Analytics\CommandCenters\Contracts;

use App\Domain\Analytics\CommandCenters\BusinessDashboardResponse;

/**
 * A Business Intelligence Command Center — the orchestration layer between the Business KPI
 * Registry, the Business KPI Calculators, the Trend Calculators, and the Report Translators.
 *
 * It ONLY orchestrates: resolve the reporting period → read KPI definitions from the registry
 * → invoke the calculators → invoke the trend calculators → invoke the report translator →
 * wrap the result in a {@see BusinessDashboardResponse}. It contains ZERO business logic — it
 * never calculates KPIs or trends, queries the database, instantiates Read Models /
 * Calculators / Translators, filters business data, computes percentages, or formats values.
 */
interface BusinessCommandCenter
{
    public function dashboard(): BusinessDashboardResponse;
}
