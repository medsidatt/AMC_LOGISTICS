<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\BillingCalculator;
use Tests\TestCase;

/** R1.3 inc5 — BillingCalculator owns billing-readiness / blocked-revenue arithmetic (pure). */
class BillingCalculatorTest extends TestCase
{
    private function calc(): BillingCalculator
    {
        return new BillingCalculator();
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
