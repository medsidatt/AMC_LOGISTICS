<?php

namespace App\Domain\Analytics\Metrics;

use Carbon\CarbonImmutable;

/**
 * The time window a descriptive metric is computed over. Immutable value object passed in by
 * the caller so Business KPI Calculators never read the clock, config, or parameters. Point-
 * in-time metrics (e.g. fleet size) ignore it.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ReportingPeriod
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}
}
