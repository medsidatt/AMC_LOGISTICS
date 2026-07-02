<?php

namespace App\Domain\Analytics\Metrics;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;

/**
 * The immutable result of computing one descriptive metric. Built once by a Business KPI
 * Calculator, never mutated. Getters only — no calculation, no formatting, no trend.
 *
 * @phpstan-consistent-constructor
 */
final readonly class MetricResult implements BusinessMetric
{
    /**
     * @param  array<string, int|float>  $components
     */
    public function __construct(
        private BusinessKpiId $id,
        private float $value,
        private MetricUnit $unit,
        private array $components = [],
    ) {}

    public function id(): BusinessKpiId
    {
        return $this->id;
    }

    public function value(): float
    {
        return $this->value;
    }

    public function unit(): MetricUnit
    {
        return $this->unit;
    }

    /** @return array<string, int|float> */
    public function components(): array
    {
        return $this->components;
    }
}
