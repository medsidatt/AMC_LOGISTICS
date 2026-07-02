<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\DispatchCalculator;
use App\Domain\Operations\Calculations\MaintenanceCalculator;
use App\Domain\Operations\Contracts\DispatchReadModelInterface;
use App\Domain\Operations\Contracts\InspectionCalculatorInterface;
use App\Domain\Operations\Contracts\InspectionReadModelInterface;
use App\Domain\Operations\Contracts\MaintenanceReadModelInterface;
use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use App\Domain\Operations\Contracts\WeightCalculatorInterface;
use App\Domain\Operations\Events\BusinessEventSeverity;
use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\BusinessOwner;
use App\Domain\Operations\Events\Derivers\DerivationContext;
use App\Domain\Operations\Events\Derivers\DispatchEventDeriver;
use App\Domain\Operations\Events\Derivers\InspectionEventDeriver;
use App\Domain\Operations\Events\Derivers\MaintenanceEventDeriver;
use App\Domain\Operations\Events\Derivers\TransportTrackingEventDeriver;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\ReadModels\Data\DispatchProjection;
use App\Domain\Operations\ReadModels\Data\ExpectedTicketProjection;
use App\Domain\Operations\ReadModels\Data\LoadProjection;
use App\Domain\Operations\ReadModels\Data\TruckInspectionProjection;
use App\Domain\Operations\ReadModels\Data\TruckMaintenanceProjection;
use App\Services\OperationalParameterService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * R3.1 — Business Event Derivers turn Read Model projections into immutable Business Events.
 * A deriver only queries Read Models, calls Calculators for every decision, and instantiates
 * events. These characterization tests isolate that orchestration: Read Models (and the
 * parameter-reading calculators) are stubbed; the Maintenance/Dispatch decisions use the real
 * calculators (DB-free with explicit inputs). They pin emit/not-emit, owner, severity, impact,
 * payload, timestamp, entity id, determinism, immutability, and the layer boundary.
 */
class BusinessEventDeriverTest extends TestCase
{
    private function context(): DerivationContext
    {
        return new DerivationContext(
            CarbonImmutable::parse('2026-07-01T12:00:00+00:00'),
            CarbonImmutable::parse('2026-06-01T00:00:00+00:00'),
            CarbonImmutable::parse('2026-06-30T23:59:59+00:00'),
        );
    }

    private function maintenanceReadModel(array $projections): MaintenanceReadModelInterface
    {
        $mock = Mockery::mock(MaintenanceReadModelInterface::class);
        $mock->shouldReceive('activeTrucksMaintenance')->andReturn(new Collection($projections));

        return $mock;
    }

    // ── Maintenance ─────────────────────────────────────────────────────────────────

    public function test_maintenance_deriver_emits_only_for_overdue_km_trucks(): void
    {
        $readModel = $this->maintenanceReadModel([
            new TruckMaintenanceProjection(1, 'AMC-01', 'kilometers', 120000.0, 10000.0, 100000.0, null), // next 110000 → overdue
            new TruckMaintenanceProjection(2, 'AMC-02', 'kilometers', 105000.0, 10000.0, 100000.0, null), // next 110000 → not overdue
            new TruckMaintenanceProjection(3, 'AMC-03', 'rotations', 999999.0, null, null, null),          // rotation-tracked → deferred/skip
        ]);
        $calculator = new MaintenanceCalculator(app(OperationalParameterService::class));

        $events = (new MaintenanceEventDeriver($readModel, $calculator))->derive($this->context());

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(EventId::MAINTENANCE_OVERDUE, $event->id());
        $this->assertSame(BusinessOwner::MAINTENANCE, $event->owner());
        $this->assertSame(BusinessEventSeverity::CRITICAL, $event->severity());
        $this->assertSame(BusinessImpact::OPERATIONAL, $event->businessImpact());
        $this->assertSame(1, $event->entityId());
        $this->assertSame('truck', $event->entityType());
        $this->assertSame(['matricule' => 'AMC-01', 'total_kilometers' => 120000.0], $event->payload());
        $this->assertEquals($this->context()->asOf, $event->occurredAt());
    }

    // ── Inspection ──────────────────────────────────────────────────────────────────

    public function test_inspection_deriver_emits_for_expired_trucks(): void
    {
        $readModel = Mockery::mock(InspectionReadModelInterface::class);
        $readModel->shouldReceive('lastInspectionByActiveTruck')->andReturn(new Collection([
            new TruckInspectionProjection(1, 'AMC-01', null),                                  // never inspected → expired
            new TruckInspectionProjection(2, 'AMC-02', new DateTimeImmutable('2026-06-25')),   // recent → valid
        ]));

        // Calculator owns the SLA read; here it is stubbed to "expired when never inspected".
        $calculator = Mockery::mock(InspectionCalculatorInterface::class);
        $calculator->shouldReceive('isExpiredForFleet')->andReturnUsing(
            fn (?DateTimeImmutable $last, DateTimeImmutable $asOf): bool => $last === null,
        );

        $events = (new InspectionEventDeriver($readModel, $calculator))->derive($this->context());

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(EventId::INSPECTION_EXPIRED, $event->id());
        $this->assertSame(BusinessOwner::HSE, $event->owner());
        $this->assertSame(BusinessEventSeverity::CRITICAL, $event->severity());
        $this->assertSame(BusinessImpact::LEGAL, $event->businessImpact());
        $this->assertSame(1, $event->entityId());
        $this->assertSame(['matricule' => 'AMC-01', 'last_inspection_date' => null], $event->payload());
    }

    // ── Weight (TransportTracking aggregate) ────────────────────────────────────────

    public function test_transport_deriver_emits_for_gap_violations_and_skips_untestable_loads(): void
    {
        $readModel = Mockery::mock(TransportTrackingReadModelInterface::class);
        $readModel->shouldReceive('loads')->andReturn(new Collection([
            new LoadProjection(10, 'AMC00010', 5, 7, 30.0, 32.0, new DateTimeImmutable('2026-06-15')), // gap 2 → violation
            new LoadProjection(11, 'AMC00011', 5, 7, 30.0, 30.1, new DateTimeImmutable('2026-06-16')), // gap 0.1 → ok
            new LoadProjection(12, 'AMC00012', 5, 7, null, 30.0, new DateTimeImmutable('2026-06-17')), // missing weight → skip
        ]));

        $calculator = Mockery::mock(WeightCalculatorInterface::class);
        $calculator->shouldReceive('isGapViolation')->andReturnUsing(fn (float $p, float $c): bool => abs($c - $p) > 0.5);
        $calculator->shouldReceive('gap')->andReturnUsing(fn (float $p, float $c): float => $c - $p);

        $events = (new TransportTrackingEventDeriver($readModel, $calculator))->derive($this->context());

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(EventId::WEIGHT_ANOMALY_DETECTED, $event->id());
        $this->assertSame(BusinessOwner::OPERATIONS, $event->owner());
        $this->assertSame(BusinessEventSeverity::HIGH, $event->severity());
        $this->assertSame(BusinessImpact::FINANCIAL, $event->businessImpact());
        $this->assertSame(10, $event->entityId());
        $this->assertSame('load', $event->entityType());
        $this->assertSame('AMC00010', $event->payload()['reference']);
        $this->assertEqualsWithDelta(2.0, $event->payload()['gap'], 0.0001);
        $this->assertEquals(new DateTimeImmutable('2026-06-15'), $event->occurredAt());
    }

    // ── Dispatch (TruckUnavailable + MissingTransportTicket) ────────────────────────

    public function test_dispatch_deriver_emits_unavailable_and_missing_ticket(): void
    {
        $readModel = Mockery::mock(DispatchReadModelInterface::class);
        $readModel->shouldReceive('program')->andReturn(new Collection([
            new DispatchProjection(100, 5, 7, new DateTimeImmutable('2026-07-01'), null),          // not started → emit
            new DispatchProjection(101, 6, 8, new DateTimeImmutable('2026-07-01'), 'EN_ROUTE'),    // started → skip
        ]));
        $readModel->shouldReceive('missingTickets')->andReturn(new Collection([
            new ExpectedTicketProjection(200, 5, 9, 100, 'missing', new DateTimeImmutable('2026-06-30T08:00:00+00:00'), null),
        ]));

        $events = (new DispatchEventDeriver($readModel, new DispatchCalculator))->derive($this->context());

        $this->assertCount(2, $events);

        $unavailable = $events[0];
        $this->assertSame(EventId::TRUCK_UNAVAILABLE, $unavailable->id());
        $this->assertSame(BusinessOwner::DISPATCH, $unavailable->owner());
        $this->assertSame(BusinessEventSeverity::CRITICAL, $unavailable->severity());
        $this->assertSame(5, $unavailable->entityId());
        $this->assertSame(100, $unavailable->payload()['dispatch_id']);

        $missing = $events[1];
        $this->assertSame(EventId::MISSING_TRANSPORT_TICKET, $missing->id());
        $this->assertSame(BusinessOwner::OPERATIONS, $missing->owner());
        $this->assertSame(BusinessImpact::FINANCIAL, $missing->businessImpact());
        $this->assertSame(200, $missing->entityId());
        $this->assertSame('expected_transport_ticket', $missing->entityType());
        $this->assertSame(100, $missing->payload()['daily_dispatch_id']);
    }

    // ── Determinism & immutability ──────────────────────────────────────────────────

    public function test_derivation_is_deterministic(): void
    {
        $projections = [new TruckMaintenanceProjection(1, 'AMC-01', 'kilometers', 120000.0, 10000.0, 100000.0, null)];
        $calc = new MaintenanceCalculator(app(OperationalParameterService::class));

        $a = (new MaintenanceEventDeriver($this->maintenanceReadModel($projections), $calc))->derive($this->context());
        $b = (new MaintenanceEventDeriver($this->maintenanceReadModel($projections), $calc))->derive($this->context());

        $this->assertEquals($a, $b);
    }

    public function test_emitted_events_are_immutable(): void
    {
        $projections = [new TruckMaintenanceProjection(1, 'AMC-01', 'kilometers', 120000.0, 10000.0, 100000.0, null)];
        $calc = new MaintenanceCalculator(app(OperationalParameterService::class));
        $event = (new MaintenanceEventDeriver($this->maintenanceReadModel($projections), $calc))->derive($this->context())[0];

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $event->entityType = 'mutated';
    }

    // ── Calculator capabilities added in Phase 2 (DB-free) ──────────────────────────

    public function test_maintenance_calculator_decides_km_overdue(): void
    {
        $calc = new MaintenanceCalculator(app(OperationalParameterService::class));

        // Explicit interval → no parameter read, so DB-free.
        $this->assertTrue($calc->isKilometersOverdue(120000.0, 100000.0, 10000.0));  // 120000 ≥ 110000
        $this->assertFalse($calc->isKilometersOverdue(105000.0, 100000.0, 10000.0)); // 105000 < 110000
        $this->assertTrue($calc->isKilometersOverdue(10000.0, null, 9000.0));        // never serviced, 10000 ≥ 9000
    }

    public function test_dispatch_calculator_decides_not_started(): void
    {
        $calc = new DispatchCalculator;

        $this->assertTrue($calc->isNotStarted(null));
        $this->assertFalse($calc->isNotStarted('EN_ROUTE'));
        $this->assertFalse($calc->isNotStarted('TERMINE'));
    }

    // ── Architecture boundary ───────────────────────────────────────────────────────

    public function test_derivers_have_no_forbidden_dependencies(): void
    {
        $forbidden = [
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'DB::' => 'the DB facade',
            '::query(' => 'a query builder',
            'config(' => 'config()',
            'env(' => 'env()',
            'OperationalParameterService' => 'the parameter service',
            'KpiRegistry' => 'the KPI registry',
            'Intelligence\\' => 'operational intelligence',
            'Translators\\' => 'translators',
            'CommandCenters\\' => 'command centers',
        ];

        foreach (glob(app_path('Domain/Operations/Events/Derivers/*.php')) as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }
}
