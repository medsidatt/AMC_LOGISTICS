<?php

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\Registry\Enums\BusinessKpiId;

/**
 * Executive BI report — a curated cross-domain view grouping the headline metrics/trends into
 * "Headline" and "Activity" sections. Which metrics reach this report is the caller's routing
 * choice; the translator only groups and orders what it is given (unmapped cards fall into an
 * "Other" section — nothing is dropped). No calculation.
 */
final class ExecutiveReportTranslator extends AbstractReportTranslator
{
    protected function reportKey(): string
    {
        return 'executive';
    }

    protected function title(): string
    {
        return 'Executive Report';
    }

    protected function sections(): array
    {
        return [
            ['key' => 'headline', 'title' => 'Headline', 'kpis' => [BusinessKpiId::OPS_001, BusinessKpiId::FLT_001, BusinessKpiId::PRD_001]],
            ['key' => 'activity', 'title' => 'Activity', 'kpis' => [BusinessKpiId::OPS_003, BusinessKpiId::FLT_003]],
        ];
    }
}
