<?php

namespace App\Domain\Analytics\Trends\Contracts;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Trends\HistorySeries;
use App\Domain\Analytics\Trends\ReportingPeriodRange;
use App\Domain\Analytics\Trends\TrendResult;

/**
 * A Trend Calculator — measures the historical movement of an already-computed BI metric.
 *
 * It consumes {@see BusinessMetric} values (produced by the R4.2 Business KPI Calculators) and
 * reporting periods, and returns a {@see TrendResult}. It NEVER recomputes a business metric,
 * reads the database or Eloquent, instantiates Read Models / Business KPI Calculators / Domain
 * Calculators, formats output, builds charts/reports, forecasts, or reads config/env. It never
 * bypasses the Business KPI Calculators — the values are handed to it.
 */
interface TrendCalculator
{
    /** Movement of one metric between two periods (both values already computed upstream). */
    public function compare(BusinessMetric $current, BusinessMetric $previous, ReportingPeriodRange $range): TrendResult;

    /** Movement of the latest two points of a history series. */
    public function trend(HistorySeries $series): TrendResult;
}
