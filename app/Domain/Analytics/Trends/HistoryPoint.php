<?php

namespace App\Domain\Analytics\Trends;

use App\Domain\Analytics\Metrics\ReportingPeriod;

/**
 * One point in a {@see HistorySeries} — an already-computed metric value at a reporting
 * period. Immutable; carries no logic. The value comes from a Business KPI Calculator (R4.2);
 * a trend never recomputes it.
 *
 * @phpstan-consistent-constructor
 */
final readonly class HistoryPoint
{
    public function __construct(
        public ReportingPeriod $period,
        public float $value,
    ) {}
}
