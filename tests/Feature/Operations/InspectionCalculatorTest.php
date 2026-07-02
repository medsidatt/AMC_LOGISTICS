<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\InspectionCalculator;
use App\Services\OperationalParameterService;
use Carbon\Carbon;
use Tests\TestCase;

/** R1.3 inc5 — InspectionCalculator owns the validity/expiry rule (pure core). */
class InspectionCalculatorTest extends TestCase
{
    private function calc(): InspectionCalculator
    {
        // The pure isValid/isExpired methods below never touch the parameter service.
        return new InspectionCalculator(app(OperationalParameterService::class));
    }

    public function test_validity_mirrors_sla_rule(): void
    {
        $calc = $this->calc();
        $asOf = Carbon::parse('2030-06-30');

        $this->assertTrue($calc->isValid(Carbon::parse('2030-06-20'), 30, $asOf));  // within 30 days
        $this->assertTrue($calc->isValid(Carbon::parse('2030-05-31'), 30, $asOf));  // exactly 30 days
        $this->assertFalse($calc->isValid(Carbon::parse('2030-05-01'), 30, $asOf)); // older than 30 days
        $this->assertFalse($calc->isValid(null, 30, $asOf));                        // never inspected
    }

    public function test_expired_is_negation(): void
    {
        $calc = $this->calc();
        $asOf = Carbon::parse('2030-06-30');
        $this->assertTrue($calc->isExpired(Carbon::parse('2030-05-01'), 30, $asOf));
        $this->assertFalse($calc->isExpired(Carbon::parse('2030-06-20'), 30, $asOf));
    }
}
