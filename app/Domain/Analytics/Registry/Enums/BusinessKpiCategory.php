<?php

namespace App\Domain\Analytics\Registry\Enums;

/**
 * The business domain a descriptive (BI) KPI belongs to. Metadata only.
 *
 * These are the Business Intelligence categories — distinct from the operational
 * `KpiCategory` (bounded-context separation). The provisional categories DISPATCH and
 * SUSTAINABILITY from the R4.1 design are **intentionally reserved** (not declared here):
 * neither holds an active or reserved KPI yet, and declaring an empty category is
 * fragmentation. They are added only when they host a real KPI.
 */
enum BusinessKpiCategory: string
{
    case FLEET = 'fleet';
    case OPERATIONS = 'operations';
    case MAINTENANCE = 'maintenance';
    case HSE = 'hse';
    case FINANCE = 'finance';
    case PRODUCTIVITY = 'productivity';

    /** The reserved identifier prefix for this category (e.g. FLT). */
    public function prefix(): string
    {
        return match ($this) {
            self::FLEET => 'FLT',
            self::OPERATIONS => 'OPS',
            self::MAINTENANCE => 'MNT',
            self::HSE => 'HSE',
            self::FINANCE => 'FIN',
            self::PRODUCTIVITY => 'PRD',
        };
    }
}
