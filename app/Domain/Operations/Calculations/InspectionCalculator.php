<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\InspectionCalculatorInterface;
use Carbon\CarbonInterface;

/**
 * Inspection validity / expiry. Pure — mirrors the existing SLA rule
 * (`inspection_date >= asOf − slaDays`). No Eloquent, SQL, config, env, or app().
 */
class InspectionCalculator implements InspectionCalculatorInterface
{
    public function isValid(?CarbonInterface $lastInspection, int $slaDays, CarbonInterface $asOf): bool
    {
        if ($lastInspection === null) {
            return false;
        }

        return $lastInspection->greaterThanOrEqualTo($asOf->copy()->subDays($slaDays));
    }

    public function isExpired(?CarbonInterface $lastInspection, int $slaDays, CarbonInterface $asOf): bool
    {
        return ! $this->isValid($lastInspection, $slaDays, $asOf);
    }
}
