<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\CapacityCalculatorInterface;
use App\Domain\Operations\Contracts\WeightCalculatorInterface;
use App\Models\FleetSetting;
use App\Models\TransportTracking;
use App\Services\DriverKpiService;
use App\Services\FleetKpiService;
use App\Services\OperationalParameterService;
use App\Services\TruckKpiService;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R1.3 increment 2 — characterization for migrating the first real consumers
 * (TransportTracking::weightGapThreshold + Driver/Truck/Fleet KPI services) onto
 * WeightCalculator + CapacityCalculator. Proves byte-identical values and DI wiring.
 */
class WeightCapacityConsumerMigrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        (new OperationalParameterSeeder())->run();
        app(OperationalParameterService::class)->flush();
    }

    /** The static shim now routes to the calculator and equals the legacy formula. */
    public function test_transport_tracking_threshold_delegates_to_calculator(): void
    {
        $calc = app(WeightCalculatorInterface::class);
        $legacy = (float) (FleetSetting::current()->weight_gap_threshold ?: 0.5);

        $this->assertSame($calc->gapThreshold(), TransportTracking::weightGapThreshold());
        $this->assertSame($legacy, TransportTracking::weightGapThreshold());
    }

    /** Capacity calculator equals the KPI services' legacy formula (param mirrors live). */
    public function test_capacity_matches_legacy_kpi_formula(): void
    {
        $calc = app(CapacityCalculatorInterface::class);
        $legacy = (float) (FleetSetting::current()->default_capacity_tonnage ?: 25);

        $this->assertSame($legacy, $calc->defaultCapacity());
    }

    /** The migrated services resolve with their calculator dependencies injected. */
    public function test_migrated_services_resolve_via_container(): void
    {
        $this->assertInstanceOf(DriverKpiService::class, app(DriverKpiService::class));
        $this->assertInstanceOf(TruckKpiService::class, app(TruckKpiService::class));
        $this->assertInstanceOf(FleetKpiService::class, app(FleetKpiService::class));
    }
}
