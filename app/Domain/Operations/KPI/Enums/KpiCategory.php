<?php

namespace App\Domain\Operations\KPI\Enums;

/**
 * The business domain a KPI belongs to (docs/kpi-catalog.md §0 reserved ranges).
 * Each category owns a permanent identifier range that is never renumbered.
 * Executive (EXEC-600+) is a consumer view reserved for future KPIs — not a
 * category that owns KPIs today, so it is intentionally absent here.
 */
enum KpiCategory: string
{
    case OPERATIONS = 'operations';
    case FINANCE = 'finance';
    case FLEET = 'fleet';
    case DISPATCH = 'dispatch';
    case MAINTENANCE = 'maintenance';
    case HSE = 'hse';

    /** The reserved identifier prefix for this category (e.g. OPS). */
    public function prefix(): string
    {
        return match ($this) {
            self::OPERATIONS => 'OPS',
            self::FINANCE => 'FIN',
            self::FLEET => 'FLT',
            self::DISPATCH => 'DSP',
            self::MAINTENANCE => 'MNT',
            self::HSE => 'HSE',
        };
    }
}
