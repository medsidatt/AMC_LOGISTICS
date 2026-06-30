<?php

namespace App\Domain\Operations\Contracts;

use Carbon\CarbonInterface;

/**
 * Owns inspection validity / expiry. Pure predicate mirroring the existing SLA rule
 * (an inspection is valid when its date is within `slaDays` of the reference date).
 * No Eloquent, SQL, config, env, or app().
 *
 * No consumer migrated in this increment: today's inspection checks are SQL queries,
 * not pure calculations. This calculator stands as the owner of the rule.
 */
interface InspectionCalculatorInterface
{
    public function isValid(?CarbonInterface $lastInspection, int $slaDays, CarbonInterface $asOf): bool;

    public function isExpired(?CarbonInterface $lastInspection, int $slaDays, CarbonInterface $asOf): bool;
}
