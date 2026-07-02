<?php

namespace App\Domain\Analytics\Metrics\Contracts;

use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;

/**
 * One computed descriptive metric value — the read-side contract a Business KPI Calculator
 * returns. It carries the KPI identity, the numeric value, its unit, and the supporting
 * components (numerator/denominator/inputs) used to produce it. No formatting, no trend, no
 * report — just the value and how it was composed.
 */
interface BusinessMetric
{
    public function id(): BusinessKpiId;

    public function value(): float;

    public function unit(): MetricUnit;

    /** @return array<string, int|float> the raw inputs behind the value (transparency, not formatting). */
    public function components(): array;
}
