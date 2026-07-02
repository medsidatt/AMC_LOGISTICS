<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\KPI\Contracts\KpiDefinitionInterface;
use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\KPI\Enums\KpiCategory;
use App\Domain\Operations\KPI\Enums\KpiDataSource;
use App\Domain\Operations\KPI\Enums\KpiId;
use App\Domain\Operations\KPI\Enums\KpiOwner;
use App\Domain\Operations\KPI\Enums\KpiRefreshStrategy;
use App\Domain\Operations\KPI\Enums\KpiSeverity;
use App\Domain\Operations\KPI\Enums\KpiUnit;
use App\Domain\Operations\KPI\KpiDefinition;
use App\Domain\Operations\KPI\KpiRegistry;
use App\Enums\OperationalParameterKey;
use ReflectionClass;
use Tests\TestCase;

/**
 * R1.5 — KPI Registry characterization. The Registry is metadata only: these tests
 * prove structure, uniqueness, immutability, and that every cross-layer reference
 * (calculator interface, read model, parameter, business event) resolves. No
 * calculation, no database, no behaviour change.
 */
class KpiRegistryTest extends TestCase
{
    /** The catalog defines exactly this many KPIs (docs/kpi-catalog.md). */
    private const EXPECTED_KPI_COUNT = 21;

    private KpiRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new KpiRegistry;
    }

    public function test_registry_loads_all_catalog_kpis(): void
    {
        $this->assertCount(self::EXPECTED_KPI_COUNT, $this->registry->all());
        $this->assertCount(self::EXPECTED_KPI_COUNT, KpiId::cases(), 'one KpiId per registered KPI');
        $this->assertContainsOnlyInstancesOf(KpiDefinition::class, $this->registry->all());
    }

    public function test_every_kpi_id_exists_exactly_once_and_no_duplicates(): void
    {
        $ids = array_map(static fn (KpiDefinition $d): string => $d->id()->value, $this->registry->all());

        $this->assertSame(count($ids), count(array_unique($ids)), 'no duplicate KPI ids');

        // Every KpiId enum case is registered exactly once — and nothing extra.
        foreach (KpiId::cases() as $case) {
            $this->assertContains($case->value, $ids, "{$case->value} must be registered");
            $this->assertTrue($this->registry->has($case));
        }
        $this->assertEqualsCanonicalizing(
            array_map(static fn (KpiId $c): string => $c->value, KpiId::cases()),
            $ids,
        );
    }

    public function test_find_returns_the_requested_definition(): void
    {
        $definition = $this->registry->find(KpiId::OPS_001);

        $this->assertInstanceOf(KpiDefinitionInterface::class, $definition);
        $this->assertSame(KpiId::OPS_001, $definition->id());
        $this->assertSame('Objective Confidence', $definition->name());
        $this->assertSame(KpiOwner::OPERATIONS, $definition->owner());
    }

    public function test_find_resolves_every_registered_id(): void
    {
        foreach (KpiId::cases() as $case) {
            $this->assertTrue($this->registry->has($case));
            $this->assertSame($case, $this->registry->find($case)->id());
        }
    }

    public function test_definitions_are_immutable(): void
    {
        $ref = new ReflectionClass(KpiDefinition::class);
        $this->assertTrue($ref->isFinal(), 'KpiDefinition must be final');
        $this->assertTrue($ref->isReadOnly(), 'KpiDefinition must be readonly');
    }

    public function test_readonly_blocks_mutation(): void
    {
        $definition = $this->registry->find(KpiId::OPS_001);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $definition->version = 99;
    }

    public function test_every_calculator_interface_exists(): void
    {
        foreach ($this->registry->all() as $definition) {
            $interface = $definition->calculatorInterface();
            $this->assertTrue(
                interface_exists($interface),
                "{$definition->id()->value} references a calculator interface that does not exist: {$interface}",
            );
        }
    }

    public function test_every_kpi_names_exactly_one_calculator(): void
    {
        foreach ($this->registry->all() as $definition) {
            $this->assertIsString($definition->calculatorInterface());
            $this->assertNotSame('', $definition->calculatorInterface());
        }
    }

    public function test_every_referenced_parameter_exists(): void
    {
        $valid = OperationalParameterKey::cases();

        foreach ($this->registry->all() as $definition) {
            foreach ($definition->parameters() as $parameter) {
                $this->assertInstanceOf(OperationalParameterKey::class, $parameter);
                $this->assertContains($parameter, $valid, "{$definition->id()->value} references an unknown parameter");
            }
        }
    }

    public function test_thresholds_are_a_subset_of_parameters(): void
    {
        foreach ($this->registry->all() as $definition) {
            foreach ($definition->thresholds() as $threshold) {
                $this->assertContains(
                    $threshold,
                    $definition->parameters(),
                    "{$definition->id()->value} threshold {$threshold->value} must also be a declared parameter",
                );
            }
        }
    }

    public function test_every_referenced_read_model_exists(): void
    {
        foreach ($this->registry->all() as $definition) {
            $this->assertNotEmpty($definition->readModels(), "{$definition->id()->value} must name at least one read model");

            foreach ($definition->readModels() as $source) {
                $this->assertInstanceOf(KpiDataSource::class, $source);
                $this->assertContains($source, KpiDataSource::cases());

                // Where a Read Model is already implemented, its contract must resolve.
                if ($source->isImplemented()) {
                    $this->assertTrue(
                        interface_exists((string) $source->contract()),
                        "{$source->value} read model contract must exist",
                    );
                }
            }
        }
    }

    public function test_every_referenced_business_event_exists(): void
    {
        $valid = EventId::cases();

        foreach ($this->registry->all() as $definition) {
            foreach ($definition->businessEvents() as $event) {
                $this->assertInstanceOf(EventId::class, $event);
                $this->assertContains($event, $valid, "{$definition->id()->value} references an unknown business event");
            }
        }
    }

    public function test_each_event_has_at_most_one_emitting_kpi(): void
    {
        // One event, one emitter — the Registry owns the mapping unambiguously so the
        // decision engine never has to choose. (Consumers relate via dependencies.)
        $emitters = [];
        foreach ($this->registry->all() as $definition) {
            foreach ($definition->businessEvents() as $event) {
                $emitters[$event->value][] = $definition->id()->value;
            }
        }

        foreach ($emitters as $eventValue => $kpis) {
            $this->assertCount(1, $kpis, "event {$eventValue} must have exactly one emitter, got: ".implode(', ', $kpis));
        }
    }

    public function test_by_event_returns_the_single_emitter_or_null(): void
    {
        $this->assertSame(KpiId::FIN_100, $this->registry->byEvent(EventId::BILLING_BLOCKED)->id());
        $this->assertSame(KpiId::FLT_201, $this->registry->byEvent(EventId::CAPACITY_REDUCED)->id());
        $this->assertSame(KpiId::OPS_001, $this->registry->byEvent(EventId::OBJECTIVE_BEHIND_SCHEDULE)->id());
        $this->assertSame(KpiId::HSE_500, $this->registry->byEvent(EventId::INSPECTION_EXPIRED)->id());

        // An event no KPI emits resolves to null (no documented decision).
        $this->assertNull($this->registry->byEvent(EventId::DISPATCH_COMPLETED));
        $this->assertNull($this->registry->byEvent(EventId::OBJECTIVE_REACHED));
    }

    public function test_every_failure_impact_is_a_known_business_impact(): void
    {
        foreach ($this->registry->all() as $definition) {
            $this->assertNotEmpty($definition->failureImpact(), "{$definition->id()->value} must declare a failure impact");
            foreach ($definition->failureImpact() as $impact) {
                $this->assertInstanceOf(BusinessImpact::class, $impact);
            }
        }
    }

    public function test_every_dependency_is_itself_a_registered_kpi(): void
    {
        foreach ($this->registry->all() as $definition) {
            foreach ($definition->dependencies() as $dependency) {
                $this->assertInstanceOf(KpiId::class, $dependency);
                $this->assertTrue(
                    $this->registry->has($dependency),
                    "{$definition->id()->value} depends on unregistered {$dependency->value}",
                );
                $this->assertNotSame($definition->id(), $dependency, 'a KPI cannot depend on itself');
            }
        }
    }

    public function test_deprecated_kpis_cannot_be_active(): void
    {
        foreach ($this->registry->all() as $definition) {
            if ($definition->deprecated()) {
                $this->assertFalse($definition->isActive());
            } else {
                $this->assertTrue($definition->isActive());
            }
        }

        // No KPI is deprecated yet, so active == all.
        $this->assertCount(self::EXPECTED_KPI_COUNT, $this->registry->active());
        $this->assertSame([], $this->registry->deprecated());
    }

    public function test_registry_filters_work_correctly(): void
    {
        // critical()
        $critical = $this->registry->critical();
        $this->assertNotEmpty($critical);
        foreach ($critical as $definition) {
            $this->assertSame(KpiSeverity::CRITICAL, $definition->severity());
        }
        // OPS-001, FIN-101, FLT-200, MNT-400, HSE-500 are the catalog's critical KPIs.
        $this->assertCount(5, $critical);

        // ownedBy()
        $financeOwned = $this->registry->ownedBy(KpiOwner::FINANCE);
        $this->assertCount(3, $financeOwned);
        foreach ($financeOwned as $definition) {
            $this->assertSame(KpiOwner::FINANCE, $definition->owner());
        }

        // inCategory()
        $this->assertCount(8, $this->registry->inCategory(KpiCategory::OPERATIONS));

        // inCommandCenter() — Executive consumes KPIs it does not own.
        $executive = $this->registry->inCommandCenter(CommandCenter::EXECUTIVE);
        $this->assertNotEmpty($executive);
        foreach ($executive as $definition) {
            $this->assertContains(CommandCenter::EXECUTIVE, $definition->commandCenters());
        }

        // bySeverity()
        $this->assertSame($this->registry->critical(), $this->registry->bySeverity(KpiSeverity::CRITICAL));
    }

    public function test_grouping_helpers_cover_every_kpi(): void
    {
        $byOwner = $this->registry->byOwner();
        $this->assertSame(self::EXPECTED_KPI_COUNT, array_sum(array_map('count', $byOwner)));

        $byCategory = $this->registry->byCategory();
        $this->assertSame(self::EXPECTED_KPI_COUNT, array_sum(array_map('count', $byCategory)));

        // byDashboard() counts each placement; KPIs shown in N centers count N times.
        $byDashboard = $this->registry->byDashboard();
        $placements = array_sum(array_map('count', $byDashboard));
        $expectedPlacements = array_sum(array_map(
            static fn (KpiDefinition $d): int => count($d->commandCenters()),
            $this->registry->all(),
        ));
        $this->assertSame($expectedPlacements, $placements);
    }

    public function test_categories_are_complete(): void
    {
        $byCategory = $this->registry->byCategory();

        foreach (KpiCategory::cases() as $category) {
            $this->assertArrayHasKey($category->value, $byCategory, "category {$category->value} has no KPI");
            $this->assertNotEmpty($byCategory[$category->value]);
        }

        // Every KPI's category matches its id prefix (e.g. KPI-OPS-001 ↔ OPS).
        foreach ($this->registry->all() as $definition) {
            $this->assertStringContainsString(
                '-'.$definition->category()->prefix().'-',
                $definition->id()->value,
                "{$definition->id()->value} prefix must match its category",
            );
        }
    }

    public function test_owners_are_complete(): void
    {
        $byOwner = $this->registry->byOwner();

        foreach (KpiOwner::cases() as $owner) {
            $this->assertArrayHasKey($owner->value, $byOwner, "owner {$owner->value} owns no KPI");
            $this->assertNotEmpty($byOwner[$owner->value]);
        }
    }

    public function test_every_definition_is_fully_populated(): void
    {
        foreach ($this->registry->all() as $definition) {
            $id = $definition->id()->value;
            $this->assertNotSame('', $definition->name(), "{$id} name");
            $this->assertNotSame('', $definition->description(), "{$id} description");
            $this->assertNotSame('', $definition->businessQuestion(), "{$id} business question");
            $this->assertNotSame('', $definition->businessDecision(), "{$id} business decision");
            $this->assertNotSame('', $definition->drillDown(), "{$id} drill-down");
            $this->assertNotSame('', $definition->requiredAction(), "{$id} required action");
            $this->assertNotSame('', $definition->successCriteria(), "{$id} success criteria");
            $this->assertNotEmpty($definition->commandCenters(), "{$id} command centers");
            $this->assertInstanceOf(KpiRefreshStrategy::class, $definition->refreshStrategy());
            $this->assertInstanceOf(KpiUnit::class, $definition->unit());
            $this->assertGreaterThanOrEqual(1, $definition->version(), "{$id} version ≥ 1");
        }
    }
}
