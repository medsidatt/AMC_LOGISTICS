<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\CapacityCalculatorInterface;
use App\Enums\OperationalParameterKey;
use App\Models\FleetSetting;
use App\Services\OperationalParameterService;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R1.3 characterization — CapacityCalculator reproduces the
 * "default_capacity_tonnage ?: fallback" logic duplicated across the KPI services.
 *
 * Demonstrates the migration prerequisite: the calculator is byte-identical to the
 * old formula ONLY once the parameter mirrors the live FleetSetting value
 * (live capacity = 41, parameter seeded 45 — see R1.3 report).
 */
class CapacityCalculatorTest extends TestCase
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

    private function calc(): CapacityCalculatorInterface
    {
        return app(CapacityCalculatorInterface::class);
    }

    public function test_default_capacity_reads_the_parameter(): void
    {
        $this->params()->set(OperationalParameterKey::DEFAULT_CAPACITY, 45);
        $this->assertSame(45.0, $this->calc()->defaultCapacity());

        $this->params()->set(OperationalParameterKey::DEFAULT_CAPACITY, 41);
        $this->assertSame(41.0, $this->calc()->defaultCapacity());
    }

    public function test_truck_capacity_falls_back_to_default(): void
    {
        $this->params()->set(OperationalParameterKey::DEFAULT_CAPACITY, 45);
        $calc = $this->calc();

        $this->assertSame(50.0, $calc->truckCapacity(50.0)); // per-truck wins
        $this->assertSame(45.0, $calc->truckCapacity(null)); // null → default
        $this->assertSame(45.0, $calc->truckCapacity(0.0));  // 0 → default
    }

    /**
     * Byte-identical to the KPI services' formula `default_capacity_tonnage ?: 25`
     * ONCE the parameter mirrors the live setting (proves the consolidation, not 25→45).
     */
    public function test_matches_old_formula_when_parameter_mirrors_live(): void
    {
        $live = (float) (FleetSetting::current()->default_capacity_tonnage ?: 25); // old KPI formula
        $this->params()->set(OperationalParameterKey::DEFAULT_CAPACITY, $live);

        $this->assertSame($live, $this->calc()->defaultCapacity());
    }
}
