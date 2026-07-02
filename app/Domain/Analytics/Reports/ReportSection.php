<?php

namespace App\Domain\Analytics\Reports;

/**
 * A named, ordered group of metric + trend cards within a report. Container only — it groups
 * and labels cards that already exist; no calculation. Immutable.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ReportSection
{
    /**
     * @param  list<MetricCard>  $metrics
     * @param  list<TrendCard>  $trends
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $metrics,
        public array $trends,
    ) {}
}
