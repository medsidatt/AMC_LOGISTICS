<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\MaintenanceCalculatorInterface;
use App\Services\OperationalParameterService;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R1.3 inc4 — MaintenanceCalculator owns both level rules:
 * statusFromRemaining (absolute, red/yellow/green) and level (interval ratio, red/orange/green).
 */
class MaintenanceCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        (new OperationalParameterSeeder())->run();
        app(OperationalParameterService::class)->flush();
    }

    private function calc(): MaintenanceCalculatorInterface
    {
        return app(MaintenanceCalculatorInterface::class);
    }

    public function test_status_from_remaining_matches_legacy(): void
    {
        $calc = $this->calc();
        $this->assertSame('red', $calc->statusFromRemaining(0.0, 500.0));
        $this->assertSame('yellow', $calc->statusFromRemaining(300.0, 500.0));
        $this->assertSame('yellow', $calc->statusFromRemaining(500.0, 500.0));
        $this->assertSame('green', $calc->statusFromRemaining(600.0, 500.0));
    }

    public function test_level_matches_legacy_truckkpi_rule(): void
    {
        $calc = $this->calc(); // ratio param = 0.1
        // interval 10000 → warning band ≤ 1000
        $this->assertSame('red', $calc->level(0.0, 10000.0));
        $this->assertSame('orange', $calc->level(500.0, 10000.0));
        $this->assertSame('orange', $calc->level(1000.0, 10000.0));
        $this->assertSame('green', $calc->level(2000.0, 10000.0));
    }
}
