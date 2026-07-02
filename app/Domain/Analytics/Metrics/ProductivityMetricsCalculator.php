<?php

namespace App\Domain\Analytics\Metrics;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Metrics\Contracts\BusinessMetricCalculator;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Operations\Contracts\CapacityCalculatorInterface;
use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use App\Domain\Operations\Contracts\UtilizationCalculatorInterface;
use InvalidArgumentException;

/**
 * Descriptive Productivity metrics: Fleet Utilization (load rate).
 *
 * Rewrites no formula — it reuses the operational `UtilizationCalculator::loadRate` (the owner
 * of the load-rate rule) and `CapacityCalculator::defaultCapacity` (owner of the capacity
 * value), feeding them the TransportTracking Read Model's period totals. Reads no DB and no
 * parameter directly (the calculators own those).
 */
final class ProductivityMetricsCalculator implements BusinessMetricCalculator
{
    private const SUPPORTED = [
        BusinessKpiId::PRD_001,
    ];

    public function __construct(
        private readonly TransportTrackingReadModelInterface $transport,
        private readonly UtilizationCalculatorInterface $utilization,
        private readonly CapacityCalculatorInterface $capacity,
    ) {}

    public function supports(BusinessKpiId $id): bool
    {
        return in_array($id, self::SUPPORTED, true);
    }

    public function compute(BusinessKpiId $id, ReportingPeriod $period): BusinessMetric
    {
        if ($id !== BusinessKpiId::PRD_001) {
            throw new InvalidArgumentException("ProductivityMetricsCalculator does not support [{$id->value}].");
        }

        $totals = $this->transport->periodTotals($period->from, $period->to);
        $capacity = $this->capacity->defaultCapacity();
        $rate = $this->utilization->loadRate($totals->clientTonnage, $capacity, $totals->trips);

        return new MetricResult($id, $rate, MetricUnit::PERCENT, [
            'tonnage' => $totals->clientTonnage,
            'capacity' => $capacity,
            'rotations' => $totals->trips,
        ]);
    }
}
