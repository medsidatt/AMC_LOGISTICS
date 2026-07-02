<?php

namespace App\Domain\Analytics\CommandCenters;

use App\Domain\Analytics\Registry\Enums\BusinessKpiId;

/**
 * Executive BI report command center — a curated cross-domain headline set. Orchestration
 * only; the concrete report scope, no logic.
 */
class ExecutiveBusinessCommandCenter extends AbstractBusinessCommandCenter
{
    protected function reportedKpis(): array
    {
        return [
            BusinessKpiId::OPS_001,
            BusinessKpiId::FLT_001,
            BusinessKpiId::PRD_001,
            BusinessKpiId::OPS_003,
            BusinessKpiId::FLT_003,
        ];
    }
}
