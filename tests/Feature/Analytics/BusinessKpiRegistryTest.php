<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Registry\BusinessKpiDefinition;
use App\Domain\Analytics\Registry\BusinessKpiRegistry;
use App\Domain\Analytics\Registry\Contracts\BusinessKpiDefinitionInterface;
use App\Domain\Analytics\Registry\Enums\Aggregation;
use App\Domain\Analytics\Registry\Enums\BusinessKpiCategory;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Analytics\Registry\Enums\RefreshCadence;
use ReflectionClass;
use Tests\TestCase;

/**
 * R4.1.0 — the Business KPI Registry is a metadata-only catalog of descriptive (BI) KPIs. It
 * calculates nothing, queries nothing, and carries none of the operational action fields.
 * These structural tests pin identity, immutability, filtering, lookup, reserved-id deferral,
 * the absence of forbidden fields, and the layer boundary.
 */
class BusinessKpiRegistryTest extends TestCase
{
    /** IDs defined (active) in the registry. */
    private const ACTIVE = [
        BusinessKpiId::FLT_001, BusinessKpiId::FLT_002, BusinessKpiId::FLT_003, BusinessKpiId::FLT_004,
        BusinessKpiId::OPS_001, BusinessKpiId::OPS_002, BusinessKpiId::OPS_003, BusinessKpiId::OPS_004, BusinessKpiId::OPS_005,
        BusinessKpiId::PRD_001,
    ];

    /** IDs reserved in the enum but deferred (no registry definition yet). */
    private const RESERVED = [
        BusinessKpiId::OPS_050, BusinessKpiId::OPS_051,
        BusinessKpiId::MNT_001, BusinessKpiId::HSE_001,
        BusinessKpiId::FIN_001, BusinessKpiId::FIN_002, BusinessKpiId::FIN_003,
        BusinessKpiId::PRD_050, BusinessKpiId::PRD_051,
    ];

    /** Operational action fields that must NEVER exist on a BI definition. */
    private const FORBIDDEN_FIELDS = [
        'severity', 'owner', 'businessDecision', 'requiredAction', 'drillDown',
        'failureImpact', 'businessEvents', 'thresholds', 'successCriteria', 'commandCenters',
    ];

    private function registry(): BusinessKpiRegistry
    {
        return new BusinessKpiRegistry;
    }

    public function test_ids_are_unique(): void
    {
        $ids = array_map(fn (BusinessKpiDefinition $d): string => $d->id()->value, $this->registry()->all());

        $this->assertSame(array_values(array_unique($ids)), $ids, 'BI KPI ids must be unique');
        $this->assertCount(count(self::ACTIVE), $ids);
    }

    public function test_categories_are_valid_and_provisional_ones_stay_reserved(): void
    {
        foreach ($this->registry()->all() as $d) {
            $this->assertInstanceOf(BusinessKpiCategory::class, $d->category());
        }

        // DISPATCH and SUSTAINABILITY were validated as provisional → not declared yet.
        $this->assertNull(BusinessKpiCategory::tryFrom('dispatch'));
        $this->assertNull(BusinessKpiCategory::tryFrom('sustainability'));
    }

    public function test_definition_is_final_readonly_and_immutable_at_runtime(): void
    {
        $ref = new ReflectionClass(BusinessKpiDefinition::class);
        $this->assertTrue($ref->isFinal());
        $this->assertTrue($ref->isReadOnly());

        $d = $this->registry()->find(BusinessKpiId::FLT_001);
        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $d->name = 'mutated';
    }

    public function test_active_filtering_works(): void
    {
        $registry = $this->registry();

        // None are deprecated in R4.1.0, so active() == all().
        $this->assertCount(count($registry->all()), $registry->active());
        foreach ($registry->active() as $d) {
            $this->assertTrue($d->active());
            $this->assertFalse($d->deprecated());
        }
    }

    public function test_category_filtering_works(): void
    {
        $fleet = $this->registry()->byCategory(BusinessKpiCategory::FLEET);
        $ids = array_map(fn (BusinessKpiDefinition $d): BusinessKpiId => $d->id(), $fleet);

        $this->assertEqualsCanonicalizing(
            [BusinessKpiId::FLT_001, BusinessKpiId::FLT_002, BusinessKpiId::FLT_003, BusinessKpiId::FLT_004],
            $ids,
        );

        // A category whose KPIs are all reserved returns nothing (no active definition).
        $this->assertSame([], $this->registry()->byCategory(BusinessKpiCategory::FINANCE));
    }

    public function test_lookup_works(): void
    {
        $registry = $this->registry();

        foreach (self::ACTIVE as $id) {
            $this->assertTrue($registry->has($id));
            $this->assertSame($id, $registry->find($id)->id());
        }
    }

    public function test_deferred_ids_remain_reserved(): void
    {
        $registry = $this->registry();

        foreach (self::RESERVED as $id) {
            // The identity is reserved in the enum …
            $this->assertInstanceOf(BusinessKpiId::class, $id);
            // … but carries no definition, and lookup fails fast.
            $this->assertFalse($registry->has($id), "{$id->value} must stay reserved (no definition)");
        }

        $this->expectException(\InvalidArgumentException::class);
        $registry->find(BusinessKpiId::FIN_001);
    }

    public function test_definition_does_not_expose_operational_action_fields(): void
    {
        foreach (self::FORBIDDEN_FIELDS as $field) {
            $this->assertFalse(
                method_exists(BusinessKpiDefinitionInterface::class, $field),
                "BusinessKpiDefinitionInterface must not expose operational field {$field}()",
            );
            $this->assertFalse(
                method_exists(BusinessKpiDefinition::class, $field),
                "BusinessKpiDefinition must not expose operational field {$field}()",
            );
        }
    }

    public function test_metadata_shape_is_descriptive_only(): void
    {
        $d = $this->registry()->find(BusinessKpiId::OPS_001);

        $this->assertInstanceOf(MetricUnit::class, $d->unit());
        $this->assertInstanceOf(Aggregation::class, $d->aggregation());
        $this->assertInstanceOf(RefreshCadence::class, $d->refreshCadence());
        $this->assertIsBool($d->trendSupport());
        $this->assertIsArray($d->readModels());
        $this->assertIsArray($d->calculators());
        $this->assertIsArray($d->reportConsumers());
    }

    public function test_registry_has_no_forbidden_dependency(): void
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

        foreach ($this->analyticsFiles() as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }

    /** @return list<string> */
    private function analyticsFiles(): array
    {
        $files = [];
        $dir = new \RecursiveDirectoryIterator(app_path('Domain/Analytics'), \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
