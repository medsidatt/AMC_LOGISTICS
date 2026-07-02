<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\DispatchReadModelInterface;
use App\Domain\Operations\Contracts\InspectionReadModelInterface;
use App\Domain\Operations\Contracts\MaintenanceReadModelInterface;
use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use App\Domain\Operations\ReadModels\Data\DispatchProjection;
use App\Domain\Operations\ReadModels\Data\ExpectedTicketProjection;
use App\Domain\Operations\ReadModels\Data\LoadProjection;
use App\Domain\Operations\ReadModels\Data\TruckInspectionProjection;
use App\Domain\Operations\ReadModels\Data\TruckMaintenanceProjection;
use App\Domain\Operations\ReadModels\DispatchReadModel;
use App\Domain\Operations\ReadModels\FleetReadModel;
use App\Domain\Operations\ReadModels\InspectionReadModel;
use App\Domain\Operations\ReadModels\MaintenanceReadModel;
use App\Domain\Operations\ReadModels\TransportTrackingReadModel;
use DateTimeImmutable;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * R3.0 — Read Model extension. The new detection projections (maintenance / inspection /
 * dispatch / ticket / per-load weight) are the sole DB readers the R3.1 Business Event
 * Derivers will consume. These tests pin the container wiring and the immutability of every
 * projection DTO — the parts that do NOT need a database.
 *
 * Query PARITY (each Read Model reproduces the current inline detection query) is a
 * DB-backed characterization gate: it requires MySQL + seed data and is run when the
 * database is available (see the class docblock note in each Read Model for the query it
 * normalizes).
 */
class ReadModelExtensionTest extends TestCase
{
    /** contract => concrete — the three new aggregate-owning Read Models */
    private const BINDINGS = [
        MaintenanceReadModelInterface::class => MaintenanceReadModel::class,
        InspectionReadModelInterface::class => InspectionReadModel::class,
        DispatchReadModelInterface::class => DispatchReadModel::class,
    ];

    private const PROJECTIONS = [
        TruckMaintenanceProjection::class,
        TruckInspectionProjection::class,
        DispatchProjection::class,
        ExpectedTicketProjection::class,
        LoadProjection::class,
    ];

    public function test_every_new_read_model_resolves_to_its_concrete(): void
    {
        foreach (self::BINDINGS as $contract => $concrete) {
            $resolved = app($contract);
            $this->assertInstanceOf($concrete, $resolved, "{$contract} must bind to {$concrete}");
            $this->assertInstanceOf($contract, $resolved);
        }
    }

    public function test_every_projection_dto_is_final_readonly(): void
    {
        foreach (self::PROJECTIONS as $class) {
            $ref = new ReflectionClass($class);
            $this->assertTrue($ref->isFinal(), "{$class} must be final");
            $this->assertTrue($ref->isReadOnly(), "{$class} must be readonly");
        }
    }

    public function test_projections_carry_raw_values_and_are_immutable_at_runtime(): void
    {
        $projection = new TruckMaintenanceProjection(1, 'AMC-01', 'kilometers', 120000.0, 10000.0, 108000.0, null);

        $this->assertSame(1, $projection->truckId);
        $this->assertSame('kilometers', $projection->maintenanceType);
        $this->assertSame(120000.0, $projection->totalKilometers);
        $this->assertSame(108000.0, $projection->lastMaintenanceKm);
        $this->assertNull($projection->lastMaintenanceDate);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $projection->totalKilometers = 0.0;
    }

    public function test_inspection_projection_holds_a_nullable_immutable_date(): void
    {
        $withDate = new TruckInspectionProjection(2, 'AMC-02', new DateTimeImmutable('2026-06-01'));
        $never = new TruckInspectionProjection(3, 'AMC-03', null);

        $this->assertInstanceOf(DateTimeImmutable::class, $withDate->lastInspectionDate);
        $this->assertNull($never->lastInspectionDate);
    }

    public function test_read_models_do_not_read_parameters_or_calculators(): void
    {
        // A Read Model normalizes DB rows only — it never resolves a parameter service, a
        // calculator, config(), or env(). Guard the layer boundary at the source level.
        $forbidden = [
            'OperationalParameterService' => 'the parameter service',
            'CalculatorInterface' => 'a calculator',
            'config(' => 'config()',
            'env(' => 'env()',
        ];

        $files = [
            app_path('Domain/Operations/ReadModels/MaintenanceReadModel.php'),
            app_path('Domain/Operations/ReadModels/InspectionReadModel.php'),
            app_path('Domain/Operations/ReadModels/DispatchReadModel.php'),
        ];

        foreach ($files as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }

    public function test_folded_methods_live_on_their_aggregate_owner(): void
    {
        // Per-load weights belong to the TransportTracking aggregate; missing tickets belong
        // to the Dispatch aggregate. No standalone LoadReadModel / TicketReadModel exists.
        $this->assertTrue(
            method_exists(TransportTrackingReadModelInterface::class, 'loads'),
            'loads() must live on TransportTrackingReadModel'
        );
        $this->assertTrue(
            method_exists(DispatchReadModelInterface::class, 'missingTickets'),
            'missingTickets() must live on DispatchReadModel'
        );

        $this->assertFalse(class_exists('App\\Domain\\Operations\\ReadModels\\LoadReadModel'));
        $this->assertFalse(class_exists('App\\Domain\\Operations\\ReadModels\\TicketReadModel'));
        $this->assertFalse(interface_exists('App\\Domain\\Operations\\Contracts\\LoadReadModelInterface'));
        $this->assertFalse(interface_exists('App\\Domain\\Operations\\Contracts\\TicketReadModelInterface'));
    }

    public function test_no_forbidden_extra_public_methods_were_added(): void
    {
        // The approved API is exact — no billingReadiness(), no dispatchStatus().
        $this->assertFalse(method_exists(TransportTrackingReadModelInterface::class, 'billingReadiness'));
        $this->assertFalse(method_exists(DispatchReadModelInterface::class, 'dispatchStatus'));
    }

    public function test_read_models_never_call_aggregate_business_methods(): void
    {
        // A Read Model is a pure query layer: it may read models/columns, but must not depend
        // on Aggregate BEHAVIOUR. These Truck maintenance accessors carry business rules.
        $forbidden = [
            'remainingKm(', 'remainingRotations(', 'usesKilometerMaintenance(',
            'maintenanceLevelByType(', 'isMaintenanceDueByType(', 'maintenanceRemainingByType(',
            'kmMaintenanceLevel(', 'kmMaintenanceInterval(', 'nextMaintenanceAtKm(',
            'maintenanceLevel(', 'maintenanceCounterByType(',
        ];

        foreach (glob(app_path('Domain/Operations/ReadModels/*.php')) as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $contents, basename($path).' must not call aggregate behaviour '.$needle.')');
            }
        }
    }

    /** class => [method => [paramCount, returnTypeShortName]] — the frozen public contract. */
    private const FROZEN_API = [
        FleetReadModel::class => [
            'activeTrucks' => [0, 'Collection'],
            'activeAvailableTrucks' => [0, 'Collection'],
            'activeTruckCount' => [0, 'int'],
            'availableCapacityTonnage' => [0, 'float'],
        ],
        TransportTrackingReadModel::class => [
            'aggregateByTruck' => [2, 'Collection'],
            'aggregateByDriver' => [2, 'Collection'],
            'periodTotals' => [2, 'PeriodTotals'],
            'monthlyTonnage' => [2, 'Collection'],
            'loads' => [2, 'Collection'],
        ],
        DispatchReadModel::class => [
            'program' => [1, 'Collection'],
            'missingTickets' => [0, 'Collection'],
        ],
        MaintenanceReadModel::class => ['activeTrucksMaintenance' => [0, 'Collection']],
        InspectionReadModel::class => ['lastInspectionByActiveTruck' => [0, 'Collection']],
    ];

    public function test_public_api_is_frozen(): void
    {
        foreach (self::FROZEN_API as $class => $expected) {
            $ref = new ReflectionClass($class);

            $own = array_filter(
                $ref->getMethods(ReflectionMethod::IS_PUBLIC),
                fn (ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === $class && $m->getName() !== '__construct',
            );

            $actualNames = array_map(fn (ReflectionMethod $m): string => $m->getName(), $own);
            sort($actualNames);
            $expectedNames = array_keys($expected);
            sort($expectedNames);

            // Fails if a method is added or removed.
            $this->assertSame($expectedNames, $actualNames, "{$class} public method set changed");

            // Fails if a signature (arity or return type) changes.
            foreach ($own as $method) {
                [$paramCount, $returnShort] = $expected[$method->getName()];
                $this->assertSame($paramCount, $method->getNumberOfParameters(), "{$class}::{$method->getName()} arity changed");

                $return = $method->getReturnType();
                $this->assertNotNull($return, "{$class}::{$method->getName()} must declare a return type");
                $name = $return->getName();
                $short = str_contains($name, '\\') ? substr((string) strrchr($name, '\\'), 1) : $name;
                $this->assertSame($returnShort, $short, "{$class}::{$method->getName()} return type changed");
            }
        }
    }
}
