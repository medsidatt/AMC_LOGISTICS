<?php

namespace App\Domain\Operations\Translators\Hse;

use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\Translators\Contracts\DashboardView;

/**
 * The HSE command center's presentation model — compliance status and inspection warnings.
 * Immutable container; holds no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class HSEView implements DashboardView
{
    public function __construct(
        private ComplianceStatus $compliance,
        private InspectionWarnings $warnings,
    ) {}

    public function compliance(): ComplianceStatus
    {
        return $this->compliance;
    }

    public function warnings(): InspectionWarnings
    {
        return $this->warnings;
    }

    public function commandCenter(): CommandCenter
    {
        return CommandCenter::HSE;
    }

    public function total(): int
    {
        return $this->compliance->total();
    }
}
