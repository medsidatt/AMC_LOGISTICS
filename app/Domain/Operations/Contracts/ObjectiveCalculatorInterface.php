<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns objective achievement / coverage arithmetic. Pure — the caller supplies the
 * already-resolved actual and target tonnages (resolved via RotationCalculator /
 * CapacityCalculator). No Eloquent, SQL, config, env, or app().
 */
interface ObjectiveCalculatorInterface
{
    /** Achievement ratio actual/target; 0 when there is no target. */
    public function achievement(float $actual, float $target): float;

    /** Coverage ratio capped at 1.0; 1.0 when there is no need. */
    public function coverage(float $allocated, float $need): float;

    /** Shortfall below target (≥ 0). */
    public function deficit(float $actual, float $target): float;

    /** Excess above target (≥ 0). */
    public function surplus(float $actual, float $target): float;

    /** Remaining tonnage to reach target (≥ 0). */
    public function remainingTarget(float $actual, float $target): float;
}
