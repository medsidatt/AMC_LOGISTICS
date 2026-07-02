<?php

namespace App\Domain\Analytics\Home;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetricCalculator;
use App\Domain\Analytics\Metrics\FleetMetricsCalculator;
use App\Domain\Analytics\Metrics\OperationsMetricsCalculator;
use App\Domain\Analytics\Metrics\ProductivityMetricsCalculator;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\BusinessKpiRegistry;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use InvalidArgumentException;

/**
 * DESCRIPTIVE data provider for the Admin Home dashboard's headline KPIs.
 *
 * It REUSES the existing BI metric calculators (the owners of each formula) to compute an
 * already-registered set of Business KPIs for the CALLER'S reporting period, and returns a flat
 * descriptive payload (id + label + unit + value + owner-supplied components). It owns no formula,
 * invents no KPI/threshold/ranking/score, reads no database directly, and builds no trend or
 * verdict — the metric OWNERS ({@see FleetMetricsCalculator} / {@see OperationsMetricsCalculator} /
 * {@see ProductivityMetricsCalculator}) do all the work, exactly as the BI Command Centers use them.
 *
 * It exists (rather than reusing an existing BI Command Center) for ONE reason: the home dashboard is
 * period-parameterized (day/week/month/year), while every {@see \App\Domain\Analytics\CommandCenters\AbstractBusinessCommandCenter}
 * is hard-locked to calendar-month windows. Editing that shared class to accept a period would change
 * the three existing BI dashboards (forbidden this phase), so this provider composes the same
 * calculator owners over the supplied period instead. Same owners, same registry gate, no duplication.
 *
 * BLOCKED — intentionally NOT provided (no existing owner; see docs/dashboard-migration-inventory.md §4):
 *   - driver count            → BI OPS_050 RESERVED (no definition)
 *   - production-target %      → BI OPS_051 RESERVED (objective-target rule not frozen)
 *   - fuel yield (L/tonne)     → FuelCalculator DORMANT, no Provider (fuel KPI catalog frozen)
 *   - top-N truck/driver rank  → no calculator/provider owns ranking
 *   - per-driver discipline     → formula owned by ProductivityCalculator, no descriptive Provider
 * These remain on the legacy path until an owner exists; this provider never fabricates one.
 */
class HomeDashboardDataProvider
{
    /**
     * The already-registered, already-owned BI KPIs the home headline reuses. No new KPI is
     * introduced here — every id is an ACTIVE definition in {@see BusinessKpiRegistry}.
     *
     * @var list<BusinessKpiId>
     */
    private const HEADLINE = [
        BusinessKpiId::FLT_001, // Fleet Size
        BusinessKpiId::FLT_002, // Available Capacity
        BusinessKpiId::FLT_003, // Fleet Availability Rate
        BusinessKpiId::FLT_004, // Fleet Saturation Rate
        BusinessKpiId::OPS_001, // Period Tonnage Delivered
        BusinessKpiId::OPS_003, // Trips
        BusinessKpiId::OPS_004, // Rotations
        BusinessKpiId::OPS_005, // Weight-Gap Exposure
        BusinessKpiId::PRD_001, // Fleet Utilization (Load Rate)
    ];

    public function __construct(
        private readonly BusinessKpiRegistry $registry,
        private readonly FleetMetricsCalculator $fleet,
        private readonly OperationsMetricsCalculator $operations,
        private readonly ProductivityMetricsCalculator $productivity,
    ) {}

    /**
     * The headline descriptive payload for the given period, keyed by BI KPI id value
     * (e.g. 'BI-FLT-003') for direct, math-free lookup in the presentation layer.
     *
     * @return array<string, array{id:string, label:string, unit:string, value:float, components:array<string,int|float>}>
     */
    public function headline(ReportingPeriod $period): array
    {
        $calculators = [$this->fleet, $this->operations, $this->productivity];

        $out = [];
        foreach (self::HEADLINE as $id) {
            $definition = $this->registry->find($id); // active-KPI gate + label/unit owner (throws if reserved)
            $metric = $this->calculatorFor($calculators, $id)->compute($id, $period);

            $out[$id->value] = [
                'id' => $id->value,
                'label' => $definition->name(),
                'unit' => $definition->unit()->value,
                'value' => $metric->value(),
                'components' => $metric->components(),
            ];
        }

        return $out;
    }

    /**
     * Route a KPI to the calculator that owns it — the same supports()-based routing the BI
     * Command Centers use. No fallback computation; if nothing owns it, that is a hard error.
     *
     * @param  list<BusinessMetricCalculator>  $calculators
     */
    private function calculatorFor(array $calculators, BusinessKpiId $id): BusinessMetricCalculator
    {
        foreach ($calculators as $calculator) {
            if ($calculator->supports($id)) {
                return $calculator;
            }
        }

        throw new InvalidArgumentException("No Business KPI Calculator supports [{$id->value}].");
    }
}
