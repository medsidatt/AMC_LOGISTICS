<?php

namespace App\Domain\Analytics\Registry;

use App\Domain\Analytics\Registry\Contracts\BusinessKpiDefinitionInterface;
use App\Domain\Analytics\Registry\Enums\Aggregation;
use App\Domain\Analytics\Registry\Enums\BusinessKpiCategory;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Analytics\Registry\Enums\RefreshCadence;
use InvalidArgumentException;

/**
 * The single authoritative catalog of descriptive (BI) KPI metadata — the Business
 * Intelligence sibling of the operational KpiRegistry. It DESCRIBES metrics and nothing else:
 *
 *   - never calculates            - never resolves a service
 *   - never executes a query      - never instantiates a Read Model / Calculator
 *   - never reads Eloquent        - never touches configuration or environment
 *   - never touches Operational Intelligence / Events / Translators / Command Centers
 *
 * Definitions are immutable value objects built from hardcoded catalog metadata and held in a
 * process memo. Only ACTIVE KPIs (backed by existing Read Models / Calculators) are defined
 * here; reserved/deferred ids exist in {@see BusinessKpiId} but carry no definition until
 * their missing dependency is provided.
 *
 * One BI KPI ID. One definition. One source of truth.
 */
final class BusinessKpiRegistry
{
    /** @var array<string, BusinessKpiDefinition>|null id-value => definition, built once. */
    private ?array $definitions = null;

    /** Resolve one BI KPI by its stable id. */
    public function find(BusinessKpiId $id): BusinessKpiDefinitionInterface
    {
        $definition = $this->definitions()[$id->value] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("No BI KPI definition registered for [{$id->value}] (reserved/deferred?).");
        }

        return $definition;
    }

    public function has(BusinessKpiId $id): bool
    {
        return isset($this->definitions()[$id->value]);
    }

    /** @return list<BusinessKpiDefinition> every defined BI KPI, catalog order. */
    public function all(): array
    {
        return array_values($this->definitions());
    }

    /** @return list<BusinessKpiDefinition> non-deprecated BI KPIs. */
    public function active(): array
    {
        return array_values(array_filter($this->all(), static fn (BusinessKpiDefinition $d): bool => $d->active()));
    }

    /** @return list<BusinessKpiDefinition> BI KPIs in one category. */
    public function byCategory(BusinessKpiCategory $category): array
    {
        return array_values(array_filter($this->all(), static fn (BusinessKpiDefinition $d): bool => $d->category() === $category));
    }

    /** @return array<string, BusinessKpiDefinition> */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $map = [];
        foreach ($this->build() as $definition) {
            $map[$definition->id()->value] = $definition;
        }

        return $this->definitions = $map;
    }

    /**
     * Every ACTIVE descriptive KPI, defined exactly once. Hardcoded metadata only. Read-model
     * and calculator references are opaque identifiers (resolved by BI calculators in R4.2).
     *
     * @return list<BusinessKpiDefinition>
     */
    private function build(): array
    {
        return [
            // ── Fleet ─────────────────────────────────────────────────────────────
            new BusinessKpiDefinition(
                id: BusinessKpiId::FLT_001,
                name: 'Fleet Size',
                category: BusinessKpiCategory::FLEET,
                businessQuestion: 'How many trucks are operating?',
                businessValue: 'Baseline for capacity planning and every per-truck ratio.',
                unit: MetricUnit::COUNT,
                aggregation: Aggregation::LATEST,
                refreshCadence: RefreshCadence::DAILY,
                trendSupport: true,
                readModels: ['FleetReadModel'],
                calculators: [],
                reportConsumers: ['bi-executive', 'bi-fleet'],
            ),
            new BusinessKpiDefinition(
                id: BusinessKpiId::FLT_002,
                name: 'Available Capacity',
                category: BusinessKpiCategory::FLEET,
                businessQuestion: 'How much tonnage can the available fleet carry?',
                businessValue: 'Shows the ceiling of what the running fleet can move.',
                unit: MetricUnit::TONNES,
                aggregation: Aggregation::LATEST,
                refreshCadence: RefreshCadence::HOURLY,
                trendSupport: true,
                readModels: ['FleetReadModel'],
                calculators: [],
                reportConsumers: ['bi-executive', 'bi-fleet'],
            ),
            new BusinessKpiDefinition(
                id: BusinessKpiId::FLT_003,
                name: 'Fleet Availability Rate',
                category: BusinessKpiCategory::FLEET,
                businessQuestion: 'What share of the fleet is available?',
                businessValue: 'Descriptive reliability of the fleet over time.',
                unit: MetricUnit::PERCENT,
                aggregation: Aggregation::RATE,
                refreshCadence: RefreshCadence::DAILY,
                trendSupport: true,
                readModels: ['FleetReadModel'],
                calculators: [],
                reportConsumers: ['bi-fleet'],
            ),
            new BusinessKpiDefinition(
                id: BusinessKpiId::FLT_004,
                name: 'Fleet Saturation Rate',
                category: BusinessKpiCategory::FLEET,
                businessQuestion: 'What share of the available fleet actually ran?',
                businessValue: 'How fully the available fleet is put to work.',
                unit: MetricUnit::PERCENT,
                aggregation: Aggregation::RATE,
                refreshCadence: RefreshCadence::DAILY,
                trendSupport: true,
                readModels: ['FleetReadModel', 'TransportTrackingReadModel'],
                calculators: [],
                reportConsumers: ['bi-fleet'],
            ),

            // ── Operations ────────────────────────────────────────────────────────
            new BusinessKpiDefinition(
                id: BusinessKpiId::OPS_001,
                name: 'Monthly Tonnage',
                category: BusinessKpiCategory::OPERATIONS,
                businessQuestion: 'How much tonnage did we deliver, month over month?',
                businessValue: 'The headline volume-performance trend.',
                unit: MetricUnit::TONNES,
                aggregation: Aggregation::SUM,
                refreshCadence: RefreshCadence::MONTHLY,
                trendSupport: true,
                readModels: ['TransportTrackingReadModel'],
                calculators: [],
                reportConsumers: ['bi-executive', 'bi-operations'],
            ),
            new BusinessKpiDefinition(
                id: BusinessKpiId::OPS_002,
                name: 'Period Tonnage Delivered',
                category: BusinessKpiCategory::OPERATIONS,
                businessQuestion: 'How much did we deliver this period?',
                businessValue: 'Period volume for reporting and comparison.',
                unit: MetricUnit::TONNES,
                aggregation: Aggregation::SUM,
                refreshCadence: RefreshCadence::DAILY,
                trendSupport: true,
                readModels: ['TransportTrackingReadModel'],
                calculators: [],
                reportConsumers: ['bi-operations'],
            ),
            new BusinessKpiDefinition(
                id: BusinessKpiId::OPS_003,
                name: 'Trips',
                category: BusinessKpiCategory::OPERATIONS,
                businessQuestion: 'How many trips were made?',
                businessValue: 'Descriptive activity volume.',
                unit: MetricUnit::COUNT,
                aggregation: Aggregation::COUNT,
                refreshCadence: RefreshCadence::DAILY,
                trendSupport: true,
                readModels: ['TransportTrackingReadModel'],
                calculators: [],
                reportConsumers: ['bi-executive', 'bi-operations'],
            ),
            new BusinessKpiDefinition(
                id: BusinessKpiId::OPS_004,
                name: 'Rotations',
                category: BusinessKpiCategory::OPERATIONS,
                businessQuestion: 'How many rotations were completed?',
                businessValue: 'Cycle activity underpinning utilization.',
                unit: MetricUnit::COUNT,
                aggregation: Aggregation::COUNT,
                refreshCadence: RefreshCadence::DAILY,
                trendSupport: true,
                readModels: ['TransportTrackingReadModel'],
                calculators: [],
                reportConsumers: ['bi-operations'],
            ),
            new BusinessKpiDefinition(
                id: BusinessKpiId::OPS_005,
                name: 'Weight-Gap Exposure',
                category: BusinessKpiCategory::OPERATIONS,
                businessQuestion: 'What is the provider↔client weight-gap tonnage trend?',
                businessValue: 'Descriptive integrity/exposure trend (not the per-load exception).',
                unit: MetricUnit::TONNES,
                aggregation: Aggregation::SUM,
                refreshCadence: RefreshCadence::DAILY,
                trendSupport: true,
                readModels: ['TransportTrackingReadModel'],
                calculators: [],
                reportConsumers: ['bi-operations', 'bi-finance'],
            ),

            // ── Productivity ────────────────────────────────────────────────────────
            new BusinessKpiDefinition(
                id: BusinessKpiId::PRD_001,
                name: 'Fleet Utilization',
                category: BusinessKpiCategory::PRODUCTIVITY,
                businessQuestion: 'How well loaded is the fleet over time?',
                businessValue: 'Descriptive load-rate trend (reuses the operational load-rate rule).',
                unit: MetricUnit::PERCENT,
                aggregation: Aggregation::RATE,
                refreshCadence: RefreshCadence::DAILY,
                trendSupport: true,
                readModels: ['TransportTrackingReadModel'],
                calculators: ['UtilizationCalculator'],
                reportConsumers: ['bi-operations', 'bi-fleet'],
            ),
        ];
    }
}
