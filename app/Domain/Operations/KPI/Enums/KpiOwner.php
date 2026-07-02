<?php

namespace App\Domain\Operations\KPI\Enums;

/**
 * The single department accountable for a KPI (docs/kpi-catalog.md, ADR-004 —
 * one owner per KPI). Mirrors the Business Event owners; the KPI layer keeps its
 * own vocabulary so it never reaches down into the Events namespace for identity.
 * Executive consumes KPIs (see CommandCenter) but owns none today.
 */
enum KpiOwner: string
{
    case OPERATIONS = 'operations';
    case FINANCE = 'finance';
    case FLEET = 'fleet';
    case DISPATCH = 'dispatch';
    case MAINTENANCE = 'maintenance';
    case HSE = 'hse';
}
