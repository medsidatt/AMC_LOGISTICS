<?php

namespace App\Domain\Analytics\Metrics\Contracts;

use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;

/**
 * A Business KPI Calculator — computes descriptive (BI) metrics for a family of KPIs.
 *
 * It consumes Read Model interfaces and MAY reuse Domain Calculator interfaces (never
 * rewriting an owned formula), and returns a {@see BusinessMetric}. It NEVER accesses the DB
 * or Eloquent, instantiates Read Models/Calculators, reads config/env, computes trends,
 * builds reports, or formats output — and it knows nothing of Business Events, Operational
 * Intelligence, Conclusions, Dashboard Translators, or Command Centers.
 */
interface BusinessMetricCalculator
{
    /** Whether this calculator computes the given BI KPI. */
    public function supports(BusinessKpiId $id): bool;

    /** Compute the metric for a supported KPI over the given window. */
    public function compute(BusinessKpiId $id, ReportingPeriod $period): BusinessMetric;
}
