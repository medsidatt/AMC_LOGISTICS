<?php

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\Trends\TrendResult;

/**
 * The presentation-ready card for one metric's movement. It copies the already-computed trend
 * facts verbatim (renaming the direction enum to its string) — no calculation, no formatting.
 * Immutable.
 *
 * @phpstan-consistent-constructor
 */
final readonly class TrendCard
{
    public function __construct(
        public string $kpiId,
        public float $currentValue,
        public float $previousValue,
        public float $difference,
        public float $percentChange,
        public string $direction,
    ) {}

    /** Build a card from an already-computed trend. Pure copy — no recomputation. */
    public static function fromTrend(TrendResult $trend): self
    {
        return new self(
            $trend->kpiId()->value,
            $trend->currentValue(),
            $trend->previousValue(),
            $trend->difference(),
            $trend->percentChange(),
            $trend->direction()->value,
        );
    }
}
