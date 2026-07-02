<?php

namespace App\Domain\Operations\KPI\Enums;

/**
 * How often a KPI is recomputed (docs/kpi-catalog.md "Refresh"). This is metadata
 * only — the Registry never schedules anything; consumers read the cadence.
 * "Real-time" means the platform's shortest poll (15 min in the catalog).
 */
enum KpiRefreshStrategy: string
{
    case REALTIME = 'realtime';
    case HOURLY = 'hourly';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
}
