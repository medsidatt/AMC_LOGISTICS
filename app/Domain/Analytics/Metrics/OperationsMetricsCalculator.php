<?php

namespace App\Domain\Analytics\Metrics;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Metrics\Contracts\BusinessMetricCalculator;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use InvalidArgumentException;

/**
 * Descriptive Operations metrics: monthly/period tonnage, trips, rotations, weight-gap
 * exposure.
 *
 * Reuses the TransportTracking Read Model's period aggregates verbatim — the sums/counts are
 * the Read Model's, not re-implemented here. It computes no trend (a single window value per
 * call), reads no DB, and reads no parameter.
 */
final class OperationsMetricsCalculator implements BusinessMetricCalculator
{
    private const SUPPORTED = [
        BusinessKpiId::OPS_001,
        BusinessKpiId::OPS_002,
        BusinessKpiId::OPS_003,
        BusinessKpiId::OPS_004,
        BusinessKpiId::OPS_005,
    ];

    public function __construct(
        private readonly TransportTrackingReadModelInterface $transport,
    ) {}

    public function supports(BusinessKpiId $id): bool
    {
        return in_array($id, self::SUPPORTED, true);
    }

    public function compute(BusinessKpiId $id, ReportingPeriod $period): BusinessMetric
    {
        return match ($id) {
            // Monthly and period tonnage are the same delivered-tonnage sum over the window
            // the caller supplies (a month vs any period); the sum is the Read Model's.
            BusinessKpiId::OPS_001, BusinessKpiId::OPS_002 => new MetricResult(
                $id,
                $this->transport->periodTotals($period->from, $period->to)->clientTonnage,
                MetricUnit::TONNES,
            ),
            BusinessKpiId::OPS_003 => new MetricResult(
                $id,
                (float) $this->transport->periodTotals($period->from, $period->to)->trips,
                MetricUnit::COUNT,
            ),
            BusinessKpiId::OPS_004 => new MetricResult(
                $id,
                (float) $this->transport->aggregateByTruck($period->from, $period->to)->sum('rotations'),
                MetricUnit::COUNT,
            ),
            BusinessKpiId::OPS_005 => new MetricResult(
                $id,
                $this->transport->periodTotals($period->from, $period->to)->gapTonnage,
                MetricUnit::TONNES,
            ),
            default => throw new InvalidArgumentException("OperationsMetricsCalculator does not support [{$id->value}]."),
        };
    }
}
