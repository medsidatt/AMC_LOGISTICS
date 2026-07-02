<?php

namespace App\Domain\Analytics\Trends;

use App\Domain\Analytics\Metrics\ReportingPeriod;

/**
 * The two windows a trend compares — the current period and the one it is measured against.
 * Immutable pair; carries no logic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ReportingPeriodRange
{
    public function __construct(
        public ReportingPeriod $current,
        public ReportingPeriod $previous,
    ) {}
}
