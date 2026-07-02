<?php

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;

/**
 * The presentation-ready card for one descriptive metric. It copies the metric's already-
 * computed value verbatim (renaming the unit enum to its string) — no calculation, no
 * number formatting, no HTML/JSON. Immutable.
 *
 * @phpstan-consistent-constructor
 */
final readonly class MetricCard
{
    /**
     * @param  array<string, int|float>  $components
     */
    public function __construct(
        public string $kpiId,
        public float $value,
        public string $unit,
        public array $components,
    ) {}

    /** Build a card from an already-computed metric. Pure copy — no transformation of the value. */
    public static function fromMetric(BusinessMetric $metric): self
    {
        return new self(
            $metric->id()->value,
            $metric->value(),
            $metric->unit()->value,
            $metric->components(),
        );
    }
}
