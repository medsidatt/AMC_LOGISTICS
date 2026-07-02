<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Metrics\FleetMetricsCalculator;
use App\Domain\Analytics\Metrics\MetricResult;
use App\Domain\Analytics\Metrics\OperationsMetricsCalculator;
use App\Domain\Analytics\Metrics\ProductivityMetricsCalculator;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Operations\Calculations\UtilizationCalculator;
use App\Domain\Operations\Contracts\CapacityCalculatorInterface;
use App\Domain\Operations\Contracts\FleetReadModelInterface;
use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use App\Domain\Operations\Contracts\UtilizationCalculatorInterface;
use App\Domain\Operations\ReadModels\Data\PeriodTotals;
use App\Domain\Operations\ReadModels\Data\TruckPeriodAggregate;
use App\Domain\Operations\ReadModels\Data\TruckProjection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * R4.2 — Business KPI Calculators compute descriptive metrics by reusing the existing Read
 * Models and Domain Calculators. These tests pin each metric's value, determinism, single
 * invocation of the Read Models / reused Domain Calculators, immutability, and the layer
 * boundary. Read Models (and the parameter-reading CapacityCalculator) are stubbed; the
 * load-rate reuse is verified against the REAL UtilizationCalculator.
 */
class BusinessMetricCalculatorTest extends TestCase
{
    private function period(): ReportingPeriod
    {
        return new ReportingPeriod(
            CarbonImmutable::parse('2026-06-01T00:00:00+00:00'),
            CarbonImmutable::parse('2026-06-30T23:59:59+00:00'),
        );
    }

    private function truckCollection(int $n): Collection
    {
        return new Collection(array_map(
            fn (int $i): TruckProjection => new TruckProjection($i, "AMC-{$i}", 45.0, 3, true, 0.95, 0.98, null),
            range(1, $n),
        ));
    }

    private function totals(): PeriodTotals
    {
        return new PeriodTotals(trips: 100, providerTonnage: 2900.0, clientTonnage: 3000.0, gapTonnage: 100.0);
    }

    private function aggregate(): Collection
    {
        return new Collection([
            new TruckPeriodAggregate(1, 40, 1200.0, 1160.0, 40.0),
            new TruckPeriodAggregate(2, 30, 900.0, 870.0, 30.0),
            new TruckPeriodAggregate(3, 30, 900.0, 870.0, 30.0),
        ]);
    }

    // ── Fleet ───────────────────────────────────────────────────────────────────────

    public function test_fleet_metrics_return_expected_values(): void
    {
        $fleet = Mockery::mock(FleetReadModelInterface::class);
        $fleet->shouldReceive('activeTruckCount')->andReturn(10);
        $fleet->shouldReceive('activeAvailableTrucks')->andReturn($this->truckCollection(8));
        $fleet->shouldReceive('availableCapacityTonnage')->andReturn(200.0);
        $transport = Mockery::mock(TransportTrackingReadModelInterface::class);
        $transport->shouldReceive('aggregateByTruck')->andReturn($this->truckCollection(6));

        $calc = new FleetMetricsCalculator($fleet, $transport);
        $p = $this->period();

        $this->assertSame(10.0, $calc->compute(BusinessKpiId::FLT_001, $p)->value());
        $this->assertSame(200.0, $calc->compute(BusinessKpiId::FLT_002, $p)->value());
        $this->assertSame(0.8, $calc->compute(BusinessKpiId::FLT_003, $p)->value());   // 8/10
        $this->assertSame(0.75, $calc->compute(BusinessKpiId::FLT_004, $p)->value());  // 6/8

        $availability = $calc->compute(BusinessKpiId::FLT_003, $p);
        $this->assertSame(MetricUnit::PERCENT, $availability->unit());
        $this->assertSame(['available' => 8, 'total' => 10], $availability->components());
    }

    // ── Operations ──────────────────────────────────────────────────────────────────

    public function test_operations_metrics_reuse_read_model_aggregates(): void
    {
        $transport = Mockery::mock(TransportTrackingReadModelInterface::class);
        $transport->shouldReceive('periodTotals')->andReturn($this->totals());
        $transport->shouldReceive('aggregateByTruck')->andReturn($this->aggregate());

        $calc = new OperationsMetricsCalculator($transport);
        $p = $this->period();

        $this->assertSame(3000.0, $calc->compute(BusinessKpiId::OPS_001, $p)->value()); // monthly tonnage
        $this->assertSame(3000.0, $calc->compute(BusinessKpiId::OPS_002, $p)->value()); // period tonnage
        $this->assertSame(100.0, $calc->compute(BusinessKpiId::OPS_003, $p)->value());  // trips
        $this->assertSame(100.0, $calc->compute(BusinessKpiId::OPS_004, $p)->value());  // rotations (40+30+30)
        $this->assertSame(100.0, $calc->compute(BusinessKpiId::OPS_005, $p)->value());  // gap tonnage
        $this->assertSame(MetricUnit::TONNES, $calc->compute(BusinessKpiId::OPS_005, $p)->unit());
    }

    // ── Productivity (reuses the REAL load-rate formula) ────────────────────────────

    public function test_utilization_reuses_the_real_load_rate_formula(): void
    {
        $transport = Mockery::mock(TransportTrackingReadModelInterface::class);
        $transport->shouldReceive('periodTotals')->andReturn($this->totals());
        $capacity = Mockery::mock(CapacityCalculatorInterface::class);
        $capacity->shouldReceive('defaultCapacity')->andReturn(45.0);

        $calc = new ProductivityMetricsCalculator($transport, new UtilizationCalculator, $capacity);

        $metric = $calc->compute(BusinessKpiId::PRD_001, $this->period());

        // loadRate(3000, 45, 100) = 3000 / (45 * 100) = 0.6667
        $this->assertEqualsWithDelta(0.6667, $metric->value(), 0.0001);
        $this->assertSame(MetricUnit::PERCENT, $metric->unit());
        $this->assertSame(['tonnage' => 3000.0, 'capacity' => 45.0, 'rotations' => 100], $metric->components());
    }

    // ── Determinism ─────────────────────────────────────────────────────────────────

    public function test_calculators_are_deterministic(): void
    {
        $transport = Mockery::mock(TransportTrackingReadModelInterface::class);
        $transport->shouldReceive('periodTotals')->andReturn($this->totals());

        $calc = new OperationsMetricsCalculator($transport);
        $p = $this->period();

        $this->assertEquals($calc->compute(BusinessKpiId::OPS_001, $p), $calc->compute(BusinessKpiId::OPS_001, $p));
    }

    // ── Single invocation of Read Models / reused Calculators ───────────────────────

    public function test_read_model_is_invoked_exactly_once_per_metric(): void
    {
        $fleet = Mockery::mock(FleetReadModelInterface::class);
        $fleet->shouldReceive('activeTruckCount')->once()->andReturn(10);
        $transport = Mockery::mock(TransportTrackingReadModelInterface::class);

        (new FleetMetricsCalculator($fleet, $transport))->compute(BusinessKpiId::FLT_001, $this->period());

        $this->assertTrue(true); // Mockery verifies ->once() on teardown
    }

    public function test_domain_calculator_is_reused_exactly_once(): void
    {
        $transport = Mockery::mock(TransportTrackingReadModelInterface::class);
        $transport->shouldReceive('periodTotals')->once()->andReturn($this->totals());
        $utilization = Mockery::mock(UtilizationCalculatorInterface::class);
        $utilization->shouldReceive('loadRate')->once()->andReturn(0.6);
        $capacity = Mockery::mock(CapacityCalculatorInterface::class);
        $capacity->shouldReceive('defaultCapacity')->once()->andReturn(45.0);

        (new ProductivityMetricsCalculator($transport, $utilization, $capacity))->compute(BusinessKpiId::PRD_001, $this->period());

        $this->assertTrue(true); // Mockery verifies each ->once()
    }

    // ── supports() routing ──────────────────────────────────────────────────────────

    public function test_each_calculator_supports_only_its_family(): void
    {
        $fleet = new FleetMetricsCalculator(Mockery::mock(FleetReadModelInterface::class), Mockery::mock(TransportTrackingReadModelInterface::class));
        $ops = new OperationsMetricsCalculator(Mockery::mock(TransportTrackingReadModelInterface::class));

        $this->assertTrue($fleet->supports(BusinessKpiId::FLT_001));
        $this->assertFalse($fleet->supports(BusinessKpiId::OPS_001));
        $this->assertTrue($ops->supports(BusinessKpiId::OPS_003));
        $this->assertFalse($ops->supports(BusinessKpiId::PRD_001));
    }

    // ── Immutability ────────────────────────────────────────────────────────────────

    public function test_metric_result_is_final_readonly_and_immutable(): void
    {
        $ref = new ReflectionClass(MetricResult::class);
        $this->assertTrue($ref->isFinal());
        $this->assertTrue($ref->isReadOnly());

        $metric = new MetricResult(BusinessKpiId::FLT_001, 10.0, MetricUnit::COUNT);
        $this->assertInstanceOf(BusinessMetric::class, $metric);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $metric->value = 0.0;
    }

    // ── Architecture boundary ───────────────────────────────────────────────────────

    public function test_calculators_have_no_forbidden_dependency(): void
    {
        $forbidden = [
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'DB::' => 'the DB facade',
            '::query(' => 'a query builder',
            'config(' => 'config()',
            'env(' => 'env()',
            'Domain\\Operations\\Intelligence' => 'operational intelligence',
            'Domain\\Operations\\Events' => 'business events',
            'Domain\\Operations\\Translators' => 'translators',
            'Domain\\Operations\\CommandCenters' => 'command centers',
        ];

        foreach (glob(app_path('Domain/Analytics/Metrics/*.php')) as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }
}
