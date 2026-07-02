<?php

namespace App\Domain\Analytics\Registry\Enums;

/**
 * How a descriptive (BI) metric is aggregated over its rows. Metadata only — the registry
 * declares the intent; the (future) BI calculators perform it.
 */
enum Aggregation: string
{
    case SUM = 'sum';
    case COUNT = 'count';
    case AVERAGE = 'average';
    case RATE = 'rate';
    case LATEST = 'latest';
}
