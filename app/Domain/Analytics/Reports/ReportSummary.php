<?php

namespace App\Domain\Analytics\Reports;

/**
 * The at-a-glance header of a report. It carries the report identity plus STRUCTURAL counts
 * (how many sections / cards were built) — these are lengths of the presentation collections,
 * not business totals/averages/percentages. Immutable.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ReportSummary
{
    public function __construct(
        public string $reportKey,
        public string $title,
        public int $sectionCount,
        public int $metricCount,
        public int $trendCount,
    ) {}
}
