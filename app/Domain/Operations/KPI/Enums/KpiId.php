<?php

namespace App\Domain\Operations\KPI\Enums;

/**
 * Stable identity of every KPI in the catalog (docs/kpi-catalog.md §0).
 * Identifiers are permanent: never renumbered, never reused once retired.
 * The case value is the business code `KPI-<DOMAIN>-NNN`.
 */
enum KpiId: string
{
    // Operations (OPS-001 → OPS-099)
    case OPS_001 = 'KPI-OPS-001'; // Objective Confidence
    case OPS_002 = 'KPI-OPS-002'; // Capacity Gap (Uncovered Volume)
    case OPS_003 = 'KPI-OPS-003'; // Production Pace Today
    case OPS_004 = 'KPI-OPS-004'; // Missing Loads (Unticketed)
    case OPS_005 = 'KPI-OPS-005'; // Weight Discrepancy Exposure
    case OPS_006 = 'KPI-OPS-006'; // Driver Productivity
    case OPS_007 = 'KPI-OPS-007'; // Provider (Quarry) Performance
    case OPS_008 = 'KPI-OPS-008'; // Average Turnaround

    // Finance (FIN-100 → FIN-199)
    case FIN_100 = 'KPI-FIN-100'; // Billing Readiness
    case FIN_101 = 'KPI-FIN-101'; // Revenue Blocked
    case FIN_102 = 'KPI-FIN-102'; // Revenue Forecast

    // Fleet (FLT-200 → FLT-299)
    case FLT_200 = 'KPI-FLT-200'; // Operational Capacity Today
    case FLT_201 = 'KPI-FLT-201'; // Capacity At Risk (This Week)
    case FLT_202 = 'KPI-FLT-202'; // Fleet Utilization
    case FLT_203 = 'KPI-FLT-203'; // Truck Productivity

    // Dispatch (DSP-300 → DSP-399)
    case DSP_300 = 'KPI-DSP-300'; // Not-Started Planned Loads
    case DSP_301 = 'KPI-DSP-301'; // Dispatch Efficiency

    // Maintenance (MNT-400 → MNT-499)
    case MNT_400 = 'KPI-MNT-400'; // Trucks At Breakdown Risk
    case MNT_401 = 'KPI-MNT-401'; // Maintenance Due (Next 7 Days)

    // Safety / HSE (HSE-500 → HSE-599)
    case HSE_500 = 'KPI-HSE-500'; // Trucks Legally Blocked
    case HSE_501 = 'KPI-HSE-501'; // Inspections Awaiting Validation
}
