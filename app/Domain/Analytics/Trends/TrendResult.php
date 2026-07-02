<?php

namespace App\Domain\Analytics\Trends;

use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Trends\Enums\TrendDirection;

/**
 * The immutable result of measuring one BI KPI's movement between two reporting periods. It
 * carries only the movement facts — no formatting, no chart, no report. Built once, never
 * mutated.
 *
 * @phpstan-consistent-constructor
 */
final readonly class TrendResult
{
    public function __construct(
        private BusinessKpiId $kpiId,
        private float $currentValue,
        private float $previousValue,
        private float $difference,
        private float $percentChange,
        private TrendDirection $direction,
        private ReportingPeriod $reportingPeriod,
        private ReportingPeriod $previousReportingPeriod,
    ) {}

    public function kpiId(): BusinessKpiId
    {
        return $this->kpiId;
    }

    public function currentValue(): float
    {
        return $this->currentValue;
    }

    public function previousValue(): float
    {
        return $this->previousValue;
    }

    public function difference(): float
    {
        return $this->difference;
    }

    public function percentChange(): float
    {
        return $this->percentChange;
    }

    public function direction(): TrendDirection
    {
        return $this->direction;
    }

    public function reportingPeriod(): ReportingPeriod
    {
        return $this->reportingPeriod;
    }

    public function previousReportingPeriod(): ReportingPeriod
    {
        return $this->previousReportingPeriod;
    }
}
