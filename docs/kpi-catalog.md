# KPI Catalog — Operational Intelligence Platform

> **Permanent business contract (R0.1).** This document is the single reference for every
> calculator, command center, report, and future contributor. **No KPI may exist outside it.**
> A KPI is an *outcome that drives a decision* — never an activity count, never a formula on screen.
> Identifiers are stable and never change. This document belongs to the business: it contains no
> implementation detail and can be read end-to-end without reading any code.
>
> Conforms to the [Platform Constitution](workspace-standard.md) and the
> [frozen architecture](operational-intelligence-architecture.md).
> **Status: proposed — awaiting approval (R0.1).** Calculators, Read Models and Parameters named
> here are defined in R1.

---

## 0. How to read a KPI contract
Each KPI specifies, without exception:

| Field | Meaning |
|---|---|
| **Identifier** | Stable code (`KPI-<DOMAIN>-NNN`). Never changes. |
| **Purpose** | Why this KPI exists. |
| **Business question** | The question it answers. |
| **Business decision** | The decision made after seeing it (mandatory). |
| **Owner** | The one department accountable. |
| **Data sources** | The Read Models that supply the data (never raw storage). |
| **Calculator** | The one Domain Calculator that owns the calculation. |
| **Parameters** | The configurable values that influence it (by key). |
| **Refresh** | Real-time · Hourly · Daily · Weekly · Monthly. |
| **Severity** | Critical · High · Medium · Low · Informational. |
| **Drill-down** | Exactly where the user goes next. |
| **Required action** | Exactly what should happen. |
| **Success criteria** | When the KPI is considered healthy. |
| **Failure impact** | What is lost if ignored (Financial / Operational / Legal / Safety / Planning / Customer). |
| **Depends on** | Other KPIs it consumes. |
| **Lifecycle** | Calculated → Consumed by → Displayed in → Drill-down → Archived. |

Domains: **Operations (OPS) · Finance (FIN) · Fleet (FLT) · Maintenance (MNT) · Dispatch (DSP) · Safety/HSE (HSE) · Executive (consumer view).**

---

## 1. Operations (OPS)

### KPI-OPS-001 · Objective Confidence
- **Purpose:** tell Operations early whether the period objective will be met while there is still time to act.
- **Business question:** Will we reach the period objective?
- **Business decision:** Whether to allocate reserve trucks / reallocate capacity now.
- **Owner:** Operations
- **Data sources:** Transport Tracking Read Model, Fleet Read Model (objective & targets)
- **Calculator:** Objective Calculator
- **Parameters:** `objective_confidence_bands`, `target_rotations_per_week`, `fiscal_month_start_day`
- **Refresh:** Hourly
- **Severity:** Critical
- **Drill-down:** Per-truck realisation vs target
- **Required action:** Allocate reserve trucks or reallocate capacity
- **Success criteria:** Projected volume ≥ objective within confidence band
- **Failure impact:** Operational + Financial — objective missed, revenue forgone
- **Depends on:** KPI-FLT-001, KPI-DSP-001, KPI-OPS-003
- **Lifecycle:** Calculated hourly → consumed by Operational Intelligence (`ObjectiveBehindSchedule`) and KPI-FIN-003 → displayed in Operations & Executive command centers → drill to per-truck realisation → archived 13-month rolling.

### KPI-OPS-002 · Capacity Gap (Uncovered Volume)
- **Purpose:** quantify the volume the current fleet cannot cover against the objective.
- **Business question:** How much volume is uncovered versus the objective?
- **Business decision:** Whether to add or reallocate capacity, or accept the shortfall.
- **Owner:** Operations
- **Data sources:** Fleet Read Model, Transport Tracking Read Model
- **Calculator:** Capacity Calculator
- **Parameters:** `default_capacity_tonnage`, `target_rotations_per_week`, `cycle_time_hours`
- **Refresh:** Hourly
- **Severity:** High
- **Drill-down:** Capacity breakdown by truck / lane
- **Required action:** Add capacity, reallocate, or escalate
- **Success criteria:** Uncovered volume = 0
- **Failure impact:** Operational + Planning — silent shortfall, missed objective
- **Depends on:** KPI-FLT-001, KPI-FLT-002
- **Lifecycle:** Calculated hourly → consumed by Intelligence (`FleetCapacityReduced`) → displayed in Operations → drill to capacity breakdown → archived 13-month rolling.

### KPI-OPS-003 · Production Pace Today
- **Purpose:** show whether today's run-rate keeps the day on track.
- **Business question:** Are we on pace today?
- **Business decision:** Whether to push the quarry / dispatch more trucks now.
- **Owner:** Operations
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Rotation Calculator
- **Parameters:** `pace_band`, `target_rotations_per_week`
- **Refresh:** Real-time (15 min)
- **Severity:** High
- **Drill-down:** Today's loads timeline
- **Required action:** Push quarry or add dispatch
- **Success criteria:** Run-rate ≥ daily pace band
- **Failure impact:** Operational — day ends short, compounds objective risk
- **Depends on:** —
- **Lifecycle:** Calculated real-time → consumed by KPI-OPS-001 → displayed in Operations → drill to today's loads → archived 90 days then aggregated.

### KPI-OPS-004 · Missing Loads (Unticketed)
- **Purpose:** surface delivered loads (seen by tracking) that have no transport ticket and therefore cannot be billed.
- **Business question:** Which delivered loads have no ticket?
- **Business decision:** Whether to create the missing tickets before the billing window closes.
- **Owner:** Operations
- **Data sources:** Dispatch Read Model, Transport Tracking Read Model
- **Calculator:** Rotation Calculator
- **Parameters:** `billable_window_days`
- **Refresh:** Hourly (and on ticket write)
- **Severity:** High
- **Drill-down:** Missing-loads queue (seeded ticket)
- **Required action:** Create the missing ticket
- **Success criteria:** 0 delivered loads without a ticket inside the billing window
- **Failure impact:** Financial — loads delivered but never invoiced
- **Depends on:** —
- **Lifecycle:** Calculated hourly → consumed by KPI-FIN-001 and Intelligence (`MissingTransportTicket`) → displayed in Operations & Finance → drill to missing-loads queue → archived 13-month rolling.

### KPI-OPS-005 · Weight Discrepancy Exposure
- **Purpose:** flag loads whose loaded vs delivered weight differs beyond tolerance — a billing and integrity risk.
- **Business question:** Which loads show a weighing problem?
- **Business decision:** Whether to verify the weighbridge ticket before invoicing.
- **Owner:** Operations
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Weight Calculator
- **Parameters:** `weight_gap_threshold_t`
- **Refresh:** Real-time (on load write)
- **Severity:** High
- **Drill-down:** Anomaly list
- **Required action:** Verify the weighbridge ticket
- **Success criteria:** 0 loads above the discrepancy threshold
- **Failure impact:** Financial + Customer — wrong invoice, dispute, loss
- **Depends on:** —
- **Lifecycle:** Calculated on write → consumed by KPI-FIN-001 and Intelligence (`WeightAnomaly`) → displayed in Operations → drill to anomaly list → archived 13-month rolling.

### KPI-OPS-006 · Driver Productivity
- **Purpose:** identify drivers who need coaching or reassignment, based on outcome not activity.
- **Business question:** Which drivers need coaching or reassignment?
- **Business decision:** Whether to coach, retrain, or reassign a driver.
- **Owner:** Operations
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Productivity Calculator
- **Parameters:** `productivity_band`, `cycle_time_hours`, `weight_gap_threshold_t`
- **Refresh:** Weekly
- **Severity:** Medium
- **Drill-down:** Driver detail
- **Required action:** Coach or reassign
- **Success criteria:** Driver within productivity band
- **Failure impact:** Operational + Planning — lost capacity, uneven performance
- **Depends on:** —
- **Lifecycle:** Calculated weekly → consumed by Operations review → displayed in Operations → drill to driver detail → archived 24-month rolling.

### KPI-OPS-007 · Provider (Quarry) Performance
- **Purpose:** reveal which loading source slows the cycle.
- **Business question:** Which quarry slows loading?
- **Business decision:** Whether to call the provider or shift volume to another source.
- **Owner:** Operations
- **Data sources:** Dispatch Read Model, Transport Tracking Read Model
- **Calculator:** Productivity Calculator
- **Parameters:** `cycle_time_hours`
- **Refresh:** Daily
- **Severity:** Medium
- **Drill-down:** Provider detail
- **Required action:** Call the provider or rebalance volume
- **Success criteria:** Loading turnaround within target
- **Failure impact:** Operational — slower cycles, reduced daily capacity
- **Depends on:** KPI-OPS-008
- **Lifecycle:** Calculated daily → consumed by Operations review → displayed in Operations → drill to provider detail → archived 13-month rolling.

### KPI-OPS-008 · Average Turnaround
- **Purpose:** measure the round-trip time that governs how many loads a truck can do.
- **Business question:** Which lane or route is slow?
- **Business decision:** Whether to investigate a bottleneck (loading, road, unloading).
- **Owner:** Operations
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Cycle Calculator
- **Parameters:** `cycle_time_hours`
- **Refresh:** Daily
- **Severity:** Medium
- **Drill-down:** Route detail
- **Required action:** Investigate the bottleneck
- **Success criteria:** Turnaround within `cycle_time_hours`
- **Failure impact:** Operational + Planning — fewer rotations per truck
- **Depends on:** —
- **Lifecycle:** Calculated daily → consumed by KPI-OPS-007 and KPI-FLT-003 → displayed in Operations → drill to route detail → archived 13-month rolling.

---

## 2. Finance (FIN)

### KPI-FIN-001 · Billing Readiness
- **Purpose:** show how much delivered tonnage is fully ready to invoice.
- **Business question:** What share of delivered tonnage is invoice-ready?
- **Business decision:** Whether to complete tickets/documents before the billing run.
- **Owner:** Finance
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Billing Calculator
- **Parameters:** `billable_window_days`, `weight_gap_threshold_t`
- **Refresh:** Hourly
- **Severity:** High
- **Drill-down:** Incomplete-tickets queue
- **Required action:** Complete the tickets / documents
- **Success criteria:** ≥ target % of delivered tonnage invoice-ready
- **Failure impact:** Financial — delayed or lost invoicing
- **Depends on:** KPI-OPS-004, KPI-OPS-005
- **Lifecycle:** Calculated hourly → consumed by KPI-FIN-002, KPI-FIN-003 and Intelligence (`BillingBlocked`) → displayed in Finance & Operations → drill to incomplete-tickets queue → archived 13-month rolling.

### KPI-FIN-002 · Revenue Blocked
- **Purpose:** put a money figure on tonnage delivered but not yet billable.
- **Business question:** How much revenue is stuck unbillable right now?
- **Business decision:** Whether to prioritise clearing the blocking documents/weights.
- **Owner:** Finance
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Billing Calculator
- **Parameters:** `revenue_rate_per_tonne`, `billable_window_days`
- **Refresh:** Hourly
- **Severity:** Critical
- **Drill-down:** Blocked-tickets queue
- **Required action:** Complete the blocking documents / weights
- **Success criteria:** Blocked revenue = 0
- **Failure impact:** Financial — cash delayed, possibly written off
- **Depends on:** KPI-FIN-001
- **Lifecycle:** Calculated hourly → consumed by Intelligence (`BillingBlocked`) and Executive view → displayed in Finance & Executive → drill to blocked-tickets queue → archived 13-month rolling.

### KPI-FIN-003 · Revenue Forecast
- **Purpose:** project period revenue from confirmed + expected billable tonnage.
- **Business question:** What revenue will the period deliver?
- **Business decision:** Whether to escalate to protect the revenue target.
- **Owner:** Finance
- **Data sources:** Transport Tracking Read Model, Fleet Read Model (objective)
- **Calculator:** Billing Calculator
- **Parameters:** `revenue_rate_per_tonne`, `objective_confidence_bands`
- **Refresh:** Daily
- **Severity:** High
- **Drill-down:** Revenue bridge (delivered / ready / forecast / at-risk)
- **Required action:** Escalate to protect the target
- **Success criteria:** Forecast ≥ revenue target
- **Failure impact:** Financial + Planning — revenue target missed
- **Depends on:** KPI-OPS-001, KPI-FIN-001
- **Lifecycle:** Calculated daily → consumed by Executive view → displayed in Finance & Executive → drill to revenue bridge → archived 24-month rolling.

---

## 3. Fleet (FLT)

### KPI-FLT-001 · Operational Capacity Today
- **Purpose:** state how much capacity can actually run today (not nominal fleet size).
- **Business question:** What capacity can operate today?
- **Business decision:** Whether today's plan is feasible with the running fleet.
- **Owner:** Fleet
- **Data sources:** Fleet Read Model, Maintenance Read Model, Inspection Read Model
- **Calculator:** Capacity Calculator
- **Parameters:** `default_capacity_tonnage`, `target_rotations_per_week`
- **Refresh:** Hourly
- **Severity:** Critical
- **Drill-down:** Fleet status (running / down / blocked)
- **Required action:** Rebalance the day's plan to running capacity
- **Success criteria:** Operational capacity ≥ planned demand
- **Failure impact:** Operational + Planning — overcommitted plan, day falls short
- **Depends on:** KPI-MNT-001, KPI-HSE-001
- **Lifecycle:** Calculated hourly → consumed by KPI-OPS-001, KPI-OPS-002, KPI-FLT-002, KPI-FLT-003 → displayed in Fleet & Executive → drill to fleet status → archived 13-month rolling.

### KPI-FLT-002 · Capacity At Risk (This Week)
- **Purpose:** warn how much capacity will be lost this week to maintenance / blocks.
- **Business question:** How much capacity will we lose this week?
- **Business decision:** Whether to pre-empt maintenance or source replacement trucks.
- **Owner:** Fleet
- **Data sources:** Fleet Read Model, Maintenance Read Model
- **Calculator:** Capacity Calculator
- **Parameters:** `maintenance_warning_pct`, `warning_threshold_km`, `max_rotations_before_maintenance`
- **Refresh:** Daily
- **Severity:** High
- **Drill-down:** At-risk trucks
- **Required action:** Pre-empt maintenance or source trucks
- **Success criteria:** Capacity at risk within planned buffer
- **Failure impact:** Operational + Planning — surprise downtime mid-week
- **Depends on:** KPI-MNT-001, KPI-HSE-001
- **Lifecycle:** Calculated daily → consumed by KPI-OPS-002 and Intelligence (`FleetCapacityReduced`) → displayed in Fleet & Executive → drill to at-risk trucks → archived 13-month rolling.

### KPI-FLT-003 · Fleet Utilization
- **Purpose:** show whether available trucks are actually being used.
- **Business question:** Are available trucks actually used?
- **Business decision:** Whether to rebalance dispatch toward idle capacity.
- **Owner:** Fleet
- **Data sources:** Fleet Read Model, Transport Tracking Read Model
- **Calculator:** Utilization Calculator
- **Parameters:** `utilization_band`, `target_rotations_per_week`
- **Refresh:** Daily
- **Severity:** Medium
- **Drill-down:** Per-truck utilization
- **Required action:** Rebalance dispatch
- **Success criteria:** Utilization within band for available trucks
- **Failure impact:** Operational — paid-for capacity wasted
- **Depends on:** KPI-FLT-001, KPI-OPS-008
- **Lifecycle:** Calculated daily → consumed by Fleet review → displayed in Fleet → drill to per-truck utilization → archived 13-month rolling.

### KPI-FLT-004 · Truck Productivity
- **Purpose:** identify trucks underperforming on output.
- **Business question:** Which trucks underperform?
- **Business decision:** Whether to investigate a truck (mechanical, route, driver).
- **Owner:** Fleet
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Productivity Calculator
- **Parameters:** `productivity_band`, `default_capacity_tonnage`
- **Refresh:** Weekly
- **Severity:** Medium
- **Drill-down:** Truck detail
- **Required action:** Investigate the truck
- **Success criteria:** Truck within productivity band
- **Failure impact:** Operational — hidden capacity loss
- **Depends on:** KPI-FLT-003
- **Lifecycle:** Calculated weekly → consumed by Fleet review → displayed in Fleet → drill to truck detail → archived 24-month rolling.

---

## 4. Maintenance (MNT)

### KPI-MNT-001 · Trucks At Breakdown Risk
- **Purpose:** name the trucks likely to stop production soon.
- **Business question:** Which trucks risk stopping production?
- **Business decision:** Whether to schedule maintenance before failure.
- **Owner:** Maintenance
- **Data sources:** Maintenance Read Model, Fleet Read Model
- **Calculator:** Maintenance Calculator
- **Parameters:** `maintenance_warning_pct`, `warning_threshold_km`, `max_rotations_before_maintenance`
- **Refresh:** Daily
- **Severity:** Critical
- **Drill-down:** Maintenance-due list
- **Required action:** Schedule maintenance
- **Success criteria:** 0 trucks past the warning threshold unscheduled
- **Failure impact:** Operational + Safety — unplanned breakdown, lost capacity
- **Depends on:** —
- **Lifecycle:** Calculated daily → consumed by KPI-FLT-001, KPI-FLT-002 and Intelligence (`MaintenanceOverdue`) → displayed in Maintenance & Fleet → drill to due list → archived 24-month rolling.

### KPI-MNT-002 · Maintenance Due (Next 7 Days)
- **Purpose:** give the workshop a forward view to plan workload.
- **Business question:** What maintenance is coming in the next 7 days?
- **Business decision:** Whether to book workshop slots and parts now.
- **Owner:** Maintenance
- **Data sources:** Maintenance Read Model
- **Calculator:** Maintenance Calculator
- **Parameters:** `max_rotations_before_maintenance`, `warning_threshold_km`
- **Refresh:** Daily
- **Severity:** Medium
- **Drill-down:** Maintenance forecast
- **Required action:** Book workshop / parts
- **Success criteria:** All upcoming due items scheduled
- **Failure impact:** Operational + Planning — workshop overload, downtime spikes
- **Depends on:** KPI-MNT-001
- **Lifecycle:** Calculated daily → consumed by Maintenance planning → displayed in Maintenance → drill to forecast → archived 13-month rolling.

---

## 5. Dispatch (DSP)

### KPI-DSP-001 · Not-Started Planned Loads
- **Purpose:** catch planned trucks that have not started, while the day can still be saved.
- **Business question:** Which planned trucks have not started?
- **Business decision:** Whether to reassign or call the driver now.
- **Owner:** Dispatch
- **Data sources:** Dispatch Read Model
- **Calculator:** Dispatch Calculator *(see Architecture Note)*
- **Parameters:** `start_deadline_hours`
- **Refresh:** Real-time (15 min)
- **Severity:** High
- **Drill-down:** Dispatch board
- **Required action:** Reassign or call the driver
- **Success criteria:** 0 planned trucks unstarted past the deadline
- **Failure impact:** Operational — lost rotations, day under plan
- **Depends on:** —
- **Lifecycle:** Calculated real-time → consumed by KPI-OPS-001 and Intelligence (`TruckUnavailable`) → displayed in Dispatch & Operations → drill to dispatch board → archived 90 days.

### KPI-DSP-002 · Dispatch Efficiency
- **Purpose:** show how plan converts to started and completed work.
- **Business question:** How much of the plan was started and completed?
- **Business decision:** Whether the planning assumptions need adjustment.
- **Owner:** Dispatch
- **Data sources:** Dispatch Read Model, Transport Tracking Read Model
- **Calculator:** Dispatch Calculator *(see Architecture Note)*
- **Parameters:** `start_deadline_hours`
- **Refresh:** Hourly
- **Severity:** Medium
- **Drill-down:** Dispatch detail
- **Required action:** Adjust planning assumptions
- **Success criteria:** Planned → completed conversion within target
- **Failure impact:** Planning — recurring plan/execution gap
- **Depends on:** KPI-DSP-001
- **Lifecycle:** Calculated hourly → consumed by Dispatch review → displayed in Dispatch → drill to dispatch detail → archived 13-month rolling.

---

## 6. Safety / HSE (HSE)

### KPI-HSE-001 · Trucks Legally Blocked
- **Purpose:** state which trucks may not legally operate (failed or expired inspection).
- **Business question:** Which trucks cannot legally operate?
- **Business decision:** Whether to validate / correct before the truck is dispatched.
- **Owner:** HSE
- **Data sources:** Inspection Read Model, Fleet Read Model
- **Calculator:** Inspection Calculator
- **Parameters:** `inspection_sla_days`
- **Refresh:** Daily
- **Severity:** Critical
- **Drill-down:** Blocked-trucks list
- **Required action:** Validate or correct the inspection
- **Success criteria:** 0 trucks legally blocked in the operating fleet
- **Failure impact:** Legal + Safety — illegal operation, liability
- **Depends on:** —
- **Lifecycle:** Calculated daily → consumed by KPI-FLT-001, KPI-FLT-002 and Intelligence (`InspectionExpired`) → displayed in HSE & Fleet → drill to blocked list → archived 24-month rolling.

### KPI-HSE-002 · Inspections Awaiting Validation
- **Purpose:** surface compliance work pending sign-off.
- **Business question:** What compliance is pending validation?
- **Business decision:** Whether to validate the pending inspections.
- **Owner:** HSE
- **Data sources:** Inspection Read Model
- **Calculator:** Inspection Calculator
- **Parameters:** `inspection_sla_days`
- **Refresh:** Daily
- **Severity:** Medium
- **Drill-down:** Validation queue
- **Required action:** Validate the inspections
- **Success criteria:** 0 inspections overdue for validation
- **Failure impact:** Legal + Safety — compliance lapses accumulate
- **Depends on:** —
- **Lifecycle:** Calculated daily → consumed by HSE workflow → displayed in HSE → drill to validation queue → archived 24-month rolling.

---

## 7. KPI dependency map
KPIs are computed bottom-up; a parent never recomputes a child — it consumes it.
```
KPI-MNT-001 Trucks At Breakdown Risk ─┐
KPI-HSE-001 Trucks Legally Blocked  ──┼─► KPI-FLT-001 Operational Capacity Today ─► KPI-FLT-002 Capacity At Risk
KPI-DSP-001 Not-Started Planned Loads ┘                 │
                                                        ▼
KPI-OPS-008 Average Turnaround ─► KPI-FLT-003 Fleet Utilization        KPI-OPS-002 Capacity Gap
KPI-OPS-004 Missing Loads ─┐
KPI-OPS-005 Weight Discrepancy ─► KPI-FIN-001 Billing Readiness ─► KPI-FIN-002 Revenue Blocked
KPI-OPS-003 Production Pace ─► KPI-OPS-001 Objective Confidence ─► KPI-FIN-003 Revenue Forecast
```

## 8. KPI relationship (value chain)
```
Fleet Availability (FLT-001)
        │
        ▼
Capacity Gap (OPS-002)  ◄── Capacity At Risk (FLT-002)
        │
        ▼
Objective Confidence (OPS-001)
        │
        ▼
Revenue Forecast (FIN-003)
```

---

## 9. Rejected KPIs (intentionally removed)
| Removed metric | Why rejected | Constitution principle | Replaced by |
|---|---|---|---|
| Truck count | Vanity — a count, not an outcome | P14 outcomes-not-activity | KPI-FLT-001 Operational Capacity Today |
| Driver count | Vanity — headcount drives no decision | P14 | KPI-OPS-006 Driver Productivity |
| Raw rotation counts | Activity, not result | P14 | KPI-OPS-003 / KPI-OPS-001 |
| Saturation rate | No decision attached | P2 action-before-information | KPI-FLT-003 Fleet Utilization |
| Raw availability rate | Not actionable as a bare % | P4 intelligence-before-reporting | KPI-FLT-001 + KPI-FLT-002 |
| Fuel per rotation | No owner, no decision | P2 | Folded into KPI-FLT-004 Truck Productivity |
| Inspection counts (week/month) | Activity volume | P14 | KPI-HSE-001 Trucks Legally Blocked |
| Pending-checklists count | A status, not a decision | P11 operational-language | KPI-HSE-002 Inspections Awaiting Validation |
| Suspicious-drivers count | Vague label, no action | P5 context-matters | KPI-OPS-005 + KPI-OPS-006 |

---

## 10. Governance rules (mandatory)
A KPI may **never**:
- calculate itself — calculation belongs to exactly one Domain Calculator;
- read the database directly — data arrives only through Read Models;
- own its thresholds — thresholds are Operational Parameters;
- exist without an owner department;
- exist without a required action;
- be computed in more than one calculator (one calculation, one owner);
- exist outside this catalog.

Every KPI must carry a stable identifier, a business decision, a single calculator, a single owner, and a defined failure impact.

---

## 11. Architecture note (for confirmation)
The [frozen architecture](operational-intelligence-architecture.md) enumerated eleven calculators. This catalog references a **Dispatch Calculator** (for KPI-DSP-001 / KPI-DSP-002) so that dispatch readiness is not overloaded onto the Rotation Calculator — keeping single-responsibility. This adds one calculator **inside** the existing Calculations layer; it does not change any layer, boundary, or rule. Flagged for explicit confirmation before R1.

Open business decisions (carried from R0): default capacity (**recommended 45 t**); the three distinct weight thresholds (`weight_gap_threshold_t` 0.5 t for KPIs, theft 300 kg, config 150 kg) — **recommended to keep distinct**, each serving a different decision.

---

## 12. Final validation
- **Can a new contributor build every command center from this document alone?** Yes — each KPI states its question, decision, owner, source, calculator, parameters, action and drill-down.
- **Can a business manager understand every KPI without reading code?** Yes — no implementation vocabulary; every KPI is phrased as a decision.
- **Can every KPI be traced to exactly one calculator?** Yes — one calculator per KPI, enforced by the governance rules.
