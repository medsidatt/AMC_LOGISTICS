<?php

namespace App\Domain\Analytics\Metrics;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Metrics\Contracts\BusinessMetricCalculator;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Operations\Contracts\FleetReadModelInterface;
use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use InvalidArgumentException;

/**
 * Descriptive Fleet metrics: size, available capacity, availability rate, saturation rate.
 *
 * Reuses the Fleet Read Model (roster/capacity) and the TransportTracking Read Model (active
 * trucks in the window). It computes only descriptive shares/counts that no Domain Calculator
 * owns; it rewrites no existing formula, reads no DB, and reads no parameter.
 */
final class FleetMetricsCalculator implements BusinessMetricCalculator
{
    private const SUPPORTED = [
        BusinessKpiId::FLT_001,
        BusinessKpiId::FLT_002,
        BusinessKpiId::FLT_003,
        BusinessKpiId::FLT_004,
    ];

    public function __construct(
        private readonly FleetReadModelInterface $fleet,
        private readonly TransportTrackingReadModelInterface $transport,
    ) {}

    public function supports(BusinessKpiId $id): bool
    {
        return in_array($id, self::SUPPORTED, true);
    }

    public function compute(BusinessKpiId $id, ReportingPeriod $period): BusinessMetric
    {
        return match ($id) {
            BusinessKpiId::FLT_001 => new MetricResult($id, (float) $this->fleet->activeTruckCount(), MetricUnit::COUNT),
            BusinessKpiId::FLT_002 => new MetricResult($id, $this->fleet->availableCapacityTonnage(), MetricUnit::TONNES),
            BusinessKpiId::FLT_003 => $this->availabilityRate($id),
            BusinessKpiId::FLT_004 => $this->saturationRate($id, $period),
            default => throw new InvalidArgumentException("FleetMetricsCalculator does not support [{$id->value}]."),
        };
    }

    private function availabilityRate(BusinessKpiId $id): MetricResult
    {
        $available = $this->fleet->activeAvailableTrucks()->count();
        $total = $this->fleet->activeTruckCount();
        $rate = $total > 0 ? $available / $total : 0.0;

        return new MetricResult($id, $rate, MetricUnit::PERCENT, ['available' => $available, 'total' => $total]);
    }

    private function saturationRate(BusinessKpiId $id, ReportingPeriod $period): MetricResult
    {
        $available = $this->fleet->activeAvailableTrucks()->count();
        $active = $this->transport->aggregateByTruck($period->from, $period->to)->count();
        $rate = $available > 0 ? $active / $available : 0.0;

        return new MetricResult($id, $rate, MetricUnit::PERCENT, ['active' => $active, 'available' => $available]);
    }
}
