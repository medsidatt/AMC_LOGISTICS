<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\WeightCalculatorInterface;
use App\Enums\OperationalParameterKey;
use App\Models\FleetSetting;
use App\Services\OperationalParameterService;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R1.3 characterization — WeightCalculator reproduces the weight-gap logic
 * currently in TransportTracking::weightGapThreshold() and the KPI services.
 */
class WeightCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        (new OperationalParameterSeeder())->run();
        app(OperationalParameterService::class)->flush();
    }

    private function params(): OperationalParameterService
    {
        return app(OperationalParameterService::class);
    }

    private function calc(): WeightCalculatorInterface
    {
        return app(WeightCalculatorInterface::class);
    }

    public function test_gap_threshold_reads_the_parameter(): void
    {
        $this->params()->set(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD, 0.5);
        $this->assertSame(0.5, $this->calc()->gapThreshold());

        $this->params()->set(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD, 0.7);
        $this->assertSame(0.7, $this->calc()->gapThreshold());
    }

    /** Byte-identical to the old formula once the parameter mirrors the live setting. */
    public function test_matches_old_formula_when_parameter_mirrors_live(): void
    {
        $live = (float) (FleetSetting::current()->weight_gap_threshold ?? 0.5); // old source
        $this->params()->set(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD, $live);

        $this->assertSame($live, $this->calc()->gapThreshold());
    }

    public function test_gap_is_client_minus_provider(): void
    {
        $this->assertSame(1.0, $this->calc()->gap(40.0, 41.0));
        $this->assertSame(-2.0, $this->calc()->gap(42.0, 40.0));
    }

    public function test_violation_reproduces_abs_gap_over_threshold(): void
    {
        $this->params()->set(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD, 0.5);
        $calc = $this->calc();

        $this->assertTrue($calc->isGapViolation(40.0, 41.0));   // |1.0| > 0.5
        $this->assertTrue($calc->isGapViolation(42.0, 40.0));   // |-2.0| > 0.5
        $this->assertFalse($calc->isGapViolation(40.0, 40.3));  // |0.3| < 0.5
        $this->assertFalse($calc->isGapViolation(40.0, 40.0));  // 0
    }
}
