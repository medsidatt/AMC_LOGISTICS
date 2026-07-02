<?php

namespace App\Domain\Analytics\Trends\Enums;

/**
 * The direction of a descriptive trend between two reporting periods. Nothing more than the
 * sign of the movement — no magnitude bands, no thresholds, no business meaning.
 */
enum TrendDirection: string
{
    case UP = 'up';
    case DOWN = 'down';
    case STABLE = 'stable';
}
