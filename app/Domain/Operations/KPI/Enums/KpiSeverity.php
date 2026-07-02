<?php

namespace App\Domain\Operations\KPI\Enums;

/**
 * How urgently a KPI breach must be acted on (docs/kpi-catalog.md, exception-first
 * ordering). Parallels the Business Event severity scale; the KPI layer owns its own.
 */
enum KpiSeverity: string
{
    case CRITICAL = 'critical';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    case INFORMATIONAL = 'informational';
}
