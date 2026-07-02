<?php

namespace App\Domain\Analytics\Reports;

/**
 * The presentation model of one BI report — a summary plus ordered sections. Immutable
 * container of already-built cards; holds no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ReportView
{
    /**
     * @param  list<ReportSection>  $sections
     */
    public function __construct(
        public ReportSummary $summary,
        public array $sections,
    ) {}
}
