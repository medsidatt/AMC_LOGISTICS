<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\ObjectiveCalculator;
use Tests\TestCase;

/** R1.3 inc5 — ObjectiveCalculator owns achievement/coverage (pure). */
class ObjectiveCalculatorTest extends TestCase
{
    private function calc(): ObjectiveCalculator
    {
        return new ObjectiveCalculator();
    }

    public function test_achievement_matches_legacy_production_target(): void
    {
        $calc = $this->calc();
        foreach ([[150.0, 200.0], [0.0, 0.0], [50.0, 0.0]] as [$delivered, $planned]) {
            $legacy = $planned > 0 ? $delivered / $planned : 0.0; // FleetKpiService:67
            $this->assertSame($legacy, $calc->achievement($delivered, $planned));
        }
    }

    public function test_coverage_is_capped_and_matches_legacy(): void
    {
        $calc = $this->calc();
        $this->assertSame(0.5, $calc->coverage(50.0, 100.0));
        $this->assertSame(1.0, $calc->coverage(150.0, 100.0)); // capped
        $this->assertSame(1.0, $calc->coverage(0.0, 0.0));     // no need
    }

    public function test_deficit_surplus_remaining(): void
    {
        $calc = $this->calc();
        $this->assertSame(20.0, $calc->deficit(80.0, 100.0));
        $this->assertSame(0.0, $calc->deficit(120.0, 100.0));
        $this->assertSame(20.0, $calc->surplus(120.0, 100.0));
        $this->assertSame(0.0, $calc->surplus(80.0, 100.0));
        $this->assertSame(20.0, $calc->remainingTarget(80.0, 100.0));
    }
}
