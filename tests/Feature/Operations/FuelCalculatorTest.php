<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\FuelCalculator;
use Tests\TestCase;

/** R1.3 inc4 — FuelCalculator owns litres/tonne yield (pure). */
class FuelCalculatorTest extends TestCase
{
    private function calc(): FuelCalculator
    {
        return new FuelCalculator();
    }

    public function test_yield_matches_legacy_formula(): void
    {
        $calc = $this->calc();
        foreach ([[100.0, 40.0], [250.0, 0.0], [0.0, 10.0]] as [$litres, $tonnage]) {
            $legacy = $tonnage > 0 ? $litres / $tonnage : null;
            $this->assertSame($legacy, $calc->yieldPerTonne($litres, $tonnage));
        }
    }

    public function test_zero_tonnage_is_null(): void
    {
        $this->assertNull($this->calc()->yieldPerTonne(500.0, 0.0));
        $this->assertSame(2.5, $this->calc()->yieldPerTonne(100.0, 40.0));
    }
}
