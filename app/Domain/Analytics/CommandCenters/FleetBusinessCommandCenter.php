<?php

namespace App\Domain\Analytics\CommandCenters;

use App\Domain\Analytics\Registry\Enums\BusinessKpiId;

/**
 * Fleet BI report command center. Orchestration only; the concrete report scope, no logic.
 */
class FleetBusinessCommandCenter extends AbstractBusinessCommandCenter
{
    protected function reportedKpis(): array
    {
        return [
            BusinessKpiId::FLT_001,
            BusinessKpiId::FLT_002,
            BusinessKpiId::FLT_003,
            BusinessKpiId::FLT_004,
            BusinessKpiId::PRD_001,
        ];
    }
}
