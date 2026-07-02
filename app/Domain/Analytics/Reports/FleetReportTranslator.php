<?php

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\Registry\Enums\BusinessKpiId;

/**
 * Fleet BI report — groups the fleet metrics/trends into "Fleet & Capacity" and
 * "Availability & Usage" sections. Grouping and ordering only; no calculation.
 */
final class FleetReportTranslator extends AbstractReportTranslator
{
    protected function reportKey(): string
    {
        return 'fleet';
    }

    protected function title(): string
    {
        return 'Fleet Report';
    }

    protected function sections(): array
    {
        return [
            ['key' => 'fleet', 'title' => 'Fleet & Capacity', 'kpis' => [BusinessKpiId::FLT_001, BusinessKpiId::FLT_002]],
            ['key' => 'usage', 'title' => 'Availability & Usage', 'kpis' => [BusinessKpiId::FLT_003, BusinessKpiId::FLT_004, BusinessKpiId::PRD_001]],
        ];
    }
}
