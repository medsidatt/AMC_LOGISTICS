<?php

namespace App\Domain\Analytics\Registry\Enums;

/**
 * Stable identity of every descriptive (BI) KPI. Identifiers are permanent — never
 * renumbered, never reused once retired. The case value is the business code
 * `BI-<CATEGORY>-NNN`, deliberately distinct from the operational `KPI-<DOMAIN>-NNN`.
 *
 * Active KPIs (001–049) are backed by existing Read Models / Calculators and are defined in
 * the BusinessKpiRegistry. Reserved KPIs (050+, and the whole of FIN/MNT/HSE) are DEFERRED —
 * their identity is reserved here but they carry no registry definition until their missing
 * dependency (a Read Model, a business rule, or a parameter) is provided. See
 * docs/backlog/deferred-business-events.md and the R4.1 design.
 */
enum BusinessKpiId: string
{
    // ── Fleet (active) ──────────────────────────────────────────────────────────
    case FLT_001 = 'BI-FLT-001'; // Fleet Size
    case FLT_002 = 'BI-FLT-002'; // Available Capacity
    case FLT_003 = 'BI-FLT-003'; // Fleet Availability Rate
    case FLT_004 = 'BI-FLT-004'; // Fleet Saturation Rate

    // ── Operations (active) ─────────────────────────────────────────────────────
    case OPS_001 = 'BI-OPS-001'; // Monthly Tonnage
    case OPS_002 = 'BI-OPS-002'; // Period Tonnage Delivered
    case OPS_003 = 'BI-OPS-003'; // Trips
    case OPS_004 = 'BI-OPS-004'; // Rotations
    case OPS_005 = 'BI-OPS-005'; // Weight-Gap Exposure

    // ── Productivity (active) ───────────────────────────────────────────────────
    case PRD_001 = 'BI-PRD-001'; // Fleet Utilization (Load Rate)

    // ── Operations (reserved / deferred) ────────────────────────────────────────
    case OPS_050 = 'BI-OPS-050'; // Driver Count            — needs a Driver Read Model
    case OPS_051 = 'BI-OPS-051'; // Production-Target %      — needs the deferred objective target rule

    // ── Maintenance (reserved / deferred) ───────────────────────────────────────
    case MNT_001 = 'BI-MNT-001'; // Maintenance Activity     — needs a maintenance-activity projection

    // ── HSE (reserved / deferred) ───────────────────────────────────────────────
    case HSE_001 = 'BI-HSE-001'; // Inspection Activity      — needs an InspectionReadModel extension

    // ── Finance (reserved / deferred) ───────────────────────────────────────────
    case FIN_001 = 'BI-FIN-001'; // Revenue                  — needs data + revenue rule/params
    case FIN_002 = 'BI-FIN-002'; // Cost                     — needs data + cost rule/params
    case FIN_003 = 'BI-FIN-003'; // Profit                   — needs Revenue + Cost

    // ── Productivity (reserved / deferred) ──────────────────────────────────────
    case PRD_050 = 'BI-PRD-050'; // Truck Productivity        — needs a scoring business rule + Fuel Read Model
    case PRD_051 = 'BI-PRD-051'; // Driver Productivity       — needs a scoring business rule + Driver Read Model
}
