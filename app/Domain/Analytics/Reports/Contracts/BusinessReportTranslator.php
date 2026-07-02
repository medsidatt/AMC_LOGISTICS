<?php

namespace App\Domain\Analytics\Reports\Contracts;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Reports\ReportResponse;
use App\Domain\Analytics\Trends\TrendResult;

/**
 * A Business Report Translator — turns already-computed metrics and trends into a
 * presentation-ready {@see ReportResponse}.
 *
 * It ONLY groups, orders, and builds cards/sections. It NEVER calculates a KPI or trend,
 * queries Read Models, instantiates Business KPI / Trend / Domain Calculators, accesses the
 * DB, reads config/env, or computes totals/averages/percentages. Same inputs → identical
 * report (deterministic); no input card is dropped.
 */
interface BusinessReportTranslator
{
    /**
     * @param  list<BusinessMetric>  $metrics  already computed by the Business KPI Calculators
     * @param  list<TrendResult>  $trends  already computed by the Trend Calculators
     */
    public function translate(array $metrics, array $trends): ReportResponse;
}
