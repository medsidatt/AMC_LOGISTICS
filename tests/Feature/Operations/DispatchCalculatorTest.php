<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\DispatchCalculator;
use Tests\TestCase;

/** R1.3 inc5 — DispatchCalculator owns dispatch ratio arithmetic (pure). */
class DispatchCalculatorTest extends TestCase
{
    private function calc(): DispatchCalculator
    {
        return new DispatchCalculator();
    }

    public function test_ratios(): void
    {
        $calc = $this->calc();
        $this->assertSame(0.3, $calc->startRate(3, 10));
        $this->assertSame(0.5, $calc->completionRate(5, 10));
        $this->assertSame(0.8, $calc->assignmentCompletion(4, 5));
    }

    public function test_zero_denominator_is_zero(): void
    {
        $calc = $this->calc();
        $this->assertSame(0.0, $calc->startRate(3, 0));
        $this->assertSame(0.0, $calc->completionRate(5, 0));
        $this->assertSame(0.0, $calc->assignmentCompletion(4, 0));
    }
}
