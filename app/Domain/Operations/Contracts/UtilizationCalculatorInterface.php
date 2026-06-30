<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns the load-rate / capacity-usage calculation. The caller supplies the capacity
 * it resolved (fleet default via CapacityCalculator, or an availability-weighted
 * average) because the capacity SOURCE differs per call site; the formula itself is
 * capacity-source-agnostic. Pure — no Eloquent, SQL, config, or events.
 */
interface UtilizationCalculatorInterface
{
    /** Load rate = tonnage / (capacity × rotations); 0 when there are no rotations. */
    public function loadRate(float $tonnage, float $capacity, int $rotations): float;
}
