<?php

namespace App\Domain\Analytics\Registry\Enums;

/**
 * How often a descriptive (BI) metric is recomputed. Metadata only — the registry never
 * schedules anything; consumers read the cadence.
 */
enum RefreshCadence: string
{
    case REALTIME = 'realtime';
    case HOURLY = 'hourly';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
}
