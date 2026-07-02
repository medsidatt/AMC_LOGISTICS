<?php

namespace App\Domain\Analytics\CommandCenters;

use App\Domain\Analytics\CommandCenters\Contracts\BusinessCommandCenter;
use App\Domain\Analytics\Metrics\Contracts\BusinessMetricCalculator;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\BusinessKpiRegistry;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Reports\Contracts\BusinessReportTranslator;
use App\Domain\Analytics\Trends\Contracts\TrendCalculator;
use App\Domain\Analytics\Trends\ReportingPeriodRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Shared orchestration for every BI Command Center. It composes the frozen BI pipeline and
 * nothing else:
 *
 *   registry (which KPIs, is-trended) → calculators (current + previous metric)
 *     → trend calculator (movement) → report translator (view) → BusinessDashboardResponse.
 *
 * Zero business logic: it computes no KPI, no trend, no percentage; it queries no database;
 * it instantiates no Read Model / Calculator / Translator (all injected). It reads the
 * registry exactly once and routes each reported KPI to the calculator that supports it.
 */
abstract class AbstractBusinessCommandCenter implements BusinessCommandCenter
{
    /**
     * @param  list<BusinessMetricCalculator>  $calculators
     */
    public function __construct(
        private readonly BusinessKpiRegistry $registry,
        private readonly array $calculators,
        private readonly TrendCalculator $trends,
        private readonly BusinessReportTranslator $translator,
    ) {}

    /** The KPIs this report covers. @return list<BusinessKpiId> */
    abstract protected function reportedKpis(): array;

    public function dashboard(): BusinessDashboardResponse
    {
        $definitions = $this->definitionMap();
        [$current, $previous] = $this->periods();
        $range = new ReportingPeriodRange($current, $previous);

        $metrics = [];
        $trendResults = [];

        foreach ($this->reportedKpis() as $id) {
            $definition = $definitions[$id->value]
                ?? throw new InvalidArgumentException("BI KPI [{$id->value}] is not an active registered metric.");

            $calculator = $this->calculatorFor($id);
            $currentMetric = $calculator->compute($id, $current);
            $metrics[] = $currentMetric;

            if ($definition->trendSupport()) {
                $previousMetric = $calculator->compute($id, $previous);
                $trendResults[] = $this->trends->compare($currentMetric, $previousMetric, $range);
            }
        }

        return new BusinessDashboardResponse(
            $this->translator->translate($metrics, $trendResults),
            CarbonImmutable::now()->toDateTimeImmutable(),
        );
    }

    /** Read the registry once; index active definitions by id for is-trended lookups. */
    private function definitionMap(): array
    {
        $map = [];
        foreach ($this->registry->active() as $definition) {
            $map[$definition->id()->value] = $definition;
        }

        return $map;
    }

    private function calculatorFor(BusinessKpiId $id): BusinessMetricCalculator
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($id)) {
                return $calculator;
            }
        }

        throw new InvalidArgumentException("No Business KPI Calculator supports [{$id->value}].");
    }

    /** Current and previous calendar-month windows, from the clock. @return array{0: ReportingPeriod, 1: ReportingPeriod} */
    private function periods(): array
    {
        $now = CarbonImmutable::now();
        $previousMonth = $now->subMonthNoOverflow();

        return [
            new ReportingPeriod($now->startOfMonth(), $now->endOfMonth()),
            new ReportingPeriod($previousMonth->startOfMonth(), $previousMonth->endOfMonth()),
        ];
    }
}
