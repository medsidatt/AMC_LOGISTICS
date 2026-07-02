<?php

namespace App\Domain\Analytics\CommandCenters;

use App\Domain\Analytics\Registry\Enums\BusinessKpiId;

/**
 * Operations BI report command center. Orchestration only; the concrete report scope, no logic.
 */
class OperationsBusinessCommandCenter extends AbstractBusinessCommandCenter
{
    protected function reportedKpis(): array
    {
        return [
            BusinessKpiId::OPS_001,
            BusinessKpiId::OPS_002,
            BusinessKpiId::OPS_003,
            BusinessKpiId::OPS_004,
            BusinessKpiId::OPS_005,
        ];
    }
}
