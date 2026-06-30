<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\FinanceCalculator;
use Tests\TestCase;

/** R1.3 inc5 — FinanceCalculator owns billing-readiness / blocked-revenue arithmetic (pure). */
class FinanceCalculatorTest extends TestCase
{
    private function calc(): FinanceCalculator
    {
        return new FinanceCalculator();
    }

    public function test_readiness_rate(): void
    {
        $calc = $this->calc();
        $this->assertSame(0.8, $calc->readinessRate(80.0, 100.0));
        $this->assertSame(0.0, $calc->readinessRate(50.0, 0.0));
    }

    public function test_blocked_revenue(): void
    {
        $this->assertSame(50000.0, $this->calc()->blockedRevenue(10.0, 5000.0));
    }
}
