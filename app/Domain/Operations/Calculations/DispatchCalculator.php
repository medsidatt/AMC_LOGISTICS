<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\DispatchCalculatorInterface;

/**
 * Dispatch readiness / completion. Pure ratio arithmetic over supplied counts.
 * No Eloquent, SQL, config, env, or app().
 */
class DispatchCalculator implements DispatchCalculatorInterface
{
    public function startRate(int $started, int $planned): float
    {
        return $planned > 0 ? $started / $planned : 0.0;
    }

    public function completionRate(int $completed, int $planned): float
    {
        return $planned > 0 ? $completed / $planned : 0.0;
    }

    public function assignmentCompletion(int $assigned, int $required): float
    {
        return $required > 0 ? $assigned / $required : 0.0;
    }
}
