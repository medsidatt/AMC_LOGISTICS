<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\UtilizationCalculator;
use Tests\TestCase;

/**
 * R1.3 inc3 — UtilizationCalculator owns the load-rate formula
 * `tonnage / (capacity × rotations)` (pure). Characterized against the legacy expression.
 */
class UtilizationCalculatorTest extends TestCase
{
    private function calc(): UtilizationCalculator
    {
        return new UtilizationCalculator();
    }

    public function test_load_rate_matches_legacy_formula(): void
    {
        $calc = $this->calc();

        foreach ([[100.0, 41.0, 2], [250.5, 45.0, 7], [0.0, 41.0, 3]] as [$tonnage, $capacity, $rotations]) {
            $legacy = $rotations > 0 ? $tonnage / ($capacity * $rotations) : 0.0;
            $this->assertSame($legacy, $calc->loadRate($tonnage, $capacity, $rotations));
        }
    }

    public function test_zero_rotations_is_zero(): void
    {
        $this->assertSame(0.0, $this->calc()->loadRate(500.0, 41.0, 0));
    }
}
