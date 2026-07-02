<?php

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\Registry\Enums\BusinessKpiId;

/**
 * Operations BI report — groups the operations metrics/trends into "Volume" and "Quality"
 * sections. Grouping and ordering only; no calculation.
 */
final class OperationsReportTranslator extends AbstractReportTranslator
{
    protected function reportKey(): string
    {
        return 'operations';
    }

    protected function title(): string
    {
        return 'Operations Report';
    }

    protected function sections(): array
    {
        return [
            ['key' => 'volume', 'title' => 'Volume', 'kpis' => [BusinessKpiId::OPS_001, BusinessKpiId::OPS_002, BusinessKpiId::OPS_003, BusinessKpiId::OPS_004]],
            ['key' => 'quality', 'title' => 'Quality', 'kpis' => [BusinessKpiId::OPS_005]],
        ];
    }
}
