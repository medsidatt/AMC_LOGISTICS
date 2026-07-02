<?php

namespace App\Domain\Operations\KPI\Enums;

/**
 * A command center (role dashboard) where a KPI is displayed (docs/kpi-catalog.md
 * "Displayed in"). A KPI is owned by exactly one department but may be CONSUMED by
 * several command centers — Executive consumes KPIs it does not own. Metadata only:
 * the Registry names destinations; it never renders or touches the UI.
 */
enum CommandCenter: string
{
    case OPERATIONS = 'operations';
    case FINANCE = 'finance';
    case FLEET = 'fleet';
    case DISPATCH = 'dispatch';
    case MAINTENANCE = 'maintenance';
    case HSE = 'hse';
    case EXECUTIVE = 'executive';
}
