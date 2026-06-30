<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\ObjectiveCalculatorInterface;

/**
 * Objective achievement / coverage. Single owner of the production-target ratio
 * (FleetKpiService) and coverage ratio (FleetOptimizerService). Pure — no Eloquent,
 * SQL, config, env, or app().
 */
class ObjectiveCalculator implements ObjectiveCalculatorInterface
{
    public function achievement(float $actual, float $target): float
    {
        return $target > 0 ? $actual / $target : 0.0;
    }

    public function coverage(float $allocated, float $need): float
    {
        return $need > 0 ? min(1.0, $allocated / $need) : 1.0;
    }

    public function deficit(float $actual, float $target): float
    {
        return max(0.0, $target - $actual);
    }

    public function surplus(float $actual, float $target): float
    {
        return max(0.0, $actual - $target);
    }

    public function remainingTarget(float $actual, float $target): float
    {
        return max(0.0, $target - $actual);
    }
}
