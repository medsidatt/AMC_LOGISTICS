<?php

namespace App\Domain\Operations\KPI\Enums;

/**
 * The unit a KPI is expressed in. Metadata only — formatting/precision live in the
 * Dashboard Translators (R1.7), never here. Centralises units that the audit found
 * scattered and inconsistent across services (e.g. fuel yield 2 vs 3 decimals).
 */
enum KpiUnit: string
{
    case PERCENT = 'percent';
    case TONNES = 'tonnes';
    case COUNT = 'count';
    case CURRENCY = 'currency';
    case HOURS = 'hours';
    case DAYS = 'days';
    case RATIO = 'ratio';
}
