<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\InspectionCalculatorInterface;
use App\Enums\OperationalParameterKey;
use App\Services\OperationalParameterService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Inspection validity / expiry. The core predicate is pure (`inspection_date >= asOf −
 * slaDays`); a fleet variant additionally owns reading the inspection SLA-days parameter so
 * callers (e.g. derivers) never resolve thresholds. No Eloquent, SQL, config, env, or app().
 */
class InspectionCalculator implements InspectionCalculatorInterface
{
    public function __construct(private readonly OperationalParameterService $parameters) {}

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

    public function isExpiredForFleet(?DateTimeInterface $lastInspection, DateTimeInterface $asOf): bool
    {
        $slaDays = (int) $this->parameters->float(OperationalParameterKey::INSPECTION_SLA_DAYS);
        $last = $lastInspection !== null ? CarbonImmutable::instance($lastInspection) : null;

        return $this->isExpired($last, $slaDays, CarbonImmutable::instance($asOf));
    }
}
