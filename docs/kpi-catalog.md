# KPI Catalog — Operational Intelligence Platform

> **Permanent business contract (R0.1 — stable).** This document is the single reference for every
> calculator, command center, report, and future contributor. **No KPI may exist outside it.**
> A KPI is an *outcome that drives a decision* — never an activity count, never a formula on screen.
> Identifiers are stable and never change. This document belongs to the business: it contains no
> implementation detail and can be read end-to-end without reading any code.
>
> Conforms to the [Platform Constitution](workspace-standard.md) and the
> [frozen architecture](operational-intelligence-architecture.md).
> Permanent decisions are recorded in the [Architecture Decision Records](#14-architecture-decision-records).

---

## 0. How to read a KPI contract
Each KPI specifies, without exception:

| Field | Meaning |
|---|---|
| **Identifier** | Stable code (`KPI-<DOMAIN>-NNN`) in a reserved range. Never changes, never renumbered. |
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

### Reserved identifier ranges (never renumber)
| Domain | Range |
|---|---|
| Operations (OPS) | OPS-001 → OPS-099 |
| Finance (FIN) | FIN-100 → FIN-199 |
| Fleet (FLT) | FLT-200 → FLT-299 |
| Dispatch (DSP) | DSP-300 → DSP-399 |
| Maintenance (MNT) | MNT-400 → MNT-499 |
| Safety / HSE (HSE) | HSE-500 → HSE-599 |
| Executive (EXEC) | EXEC-600 → EXEC-699 *(consumer view; reserved for future executive-owned KPIs)* |
| *Reserved — future* | Predictive PRD-700→799 · Optimization OPT-800→899 · AI Insights AIX-900→999 (see §13) |

A retired KPI's identifier is **never reused**; it is marked retired in §9.

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
- **Depends on:** KPI-FLT-200, KPI-DSP-300, KPI-OPS-003
- **Lifecycle:** Calculated hourly → consumed by Intelligence (`ObjectiveBehindSchedule`) and KPI-FIN-102 → displayed in Operations & Executive command centers → drill to per-truck realisation → archived 13-month rolling.

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
- **Depends on:** KPI-FLT-200, KPI-FLT-201
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
- **Lifecycle:** Calculated hourly → consumed by KPI-FIN-100 and Intelligence (`MissingTransportTicket`) → displayed in Operations & Finance → drill to missing-loads queue → archived 13-month rolling.

### KPI-OPS-005 · Weight Discrepancy Exposure
- **Purpose:** flag loads whose loaded vs delivered weight differs beyond the daily operational tolerance — a billing and integrity risk.
- **Business question:** Which loads show a weighing problem?
- **Business decision:** Whether to verify the weighbridge ticket before invoicing.
- **Owner:** Operations
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Weight Calculator
- **Parameters:** `weight_operational_threshold_t` (operational threshold — see §9)
- **Refresh:** Real-time (on load write)
- **Severity:** High
- **Drill-down:** Anomaly list
- **Required action:** Verify the weighbridge ticket
- **Success criteria:** 0 loads above the operational discrepancy threshold
- **Failure impact:** Financial + Customer — wrong invoice, dispute, loss
- **Depends on:** —
- **Lifecycle:** Calculated on write → consumed by KPI-FIN-100 and Intelligence (`WeightAnomaly`) → displayed in Operations → drill to anomaly list → archived 13-month rolling.

### KPI-OPS-006 · Driver Productivity
- **Purpose:** identify drivers who need coaching or reassignment, based on outcome not activity.
- **Business question:** Which drivers need coaching or reassignment?
- **Business decision:** Whether to coach, retrain, or reassign a driver.
- **Owner:** Operations
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Productivity Calculator
- **Parameters:** `productivity_band`, `cycle_time_hours`, `weight_operational_threshold_t`
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
- **Lifecycle:** Calculated daily → consumed by KPI-OPS-007 and KPI-FLT-202 → displayed in Operations → drill to route detail → archived 13-month rolling.

---

## 2. Finance (FIN)

### KPI-FIN-100 · Billing Readiness
- **Purpose:** show how much delivered tonnage is fully ready to invoice.
- **Business question:** What share of delivered tonnage is invoice-ready?
- **Business decision:** Whether to complete tickets/documents before the billing run.
- **Owner:** Finance
- **Data sources:** Transport Tracking Read Model
- **Calculator:** Billing Calculator
- **Parameters:** `billable_window_days`, `weight_operational_threshold_t`
- **Refresh:** Hourly
- **Severity:** High
- **Drill-down:** Incomplete-tickets queue
- **Required action:** Complete the tickets / documents
- **Success criteria:** ≥ target % of delivered tonnage invoice-ready
- **Failure impact:** Financial — delayed or lost invoicing
- **Depends on:** KPI-OPS-004, KPI-OPS-005
- **Lifecycle:** Calculated hourly → consumed by KPI-FIN-101, KPI-FIN-102 and Intelligence (`BillingBlocked`) → displayed in Finance & Operations → drill to incomplete-tickets queue → archived 13-month rolling.

### KPI-FIN-101 · Revenue Blocked
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
- **Depends on:** KPI-FIN-100
- **Lifecycle:** Calculated hourly → consumed by Intelligence (`BillingBlocked`) and Executive view → displayed in Finance & Executive → drill to blocked-tickets queue → archived 13-month rolling.

### KPI-FIN-102 · Revenue Forecast
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
- **Depends on:** KPI-OPS-001, KPI-FIN-100
- **Lifecycle:** Calculated daily → consumed by Executive view → displayed in Finance & Executive → drill to revenue bridge → archived 24-month rolling.

---

## 3. Fleet (FLT)

### KPI-FLT-200 · Operational Capacity Today
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
- **Depends on:** KPI-MNT-400, KPI-HSE-500
- **Lifecycle:** Calculated hourly → consumed by KPI-OPS-001, KPI-OPS-002, KPI-FLT-201, KPI-FLT-202 → displayed in Fleet & Executive → drill to fleet status → archived 13-month rolling.

### KPI-FLT-201 · Capacity At Risk (This Week)
- **Purpose:** warn how much capacity will be lost this week to maintenance / blocks.
- **Business question:** How much capacity will we lose this week?
- **Business decision:** Whether to pre-empt maintenance or source replacement trucks.
- **Owner:** Fleet
- **Data sources:** Fleet Read Model, Maintenance Read Model
- **Calculator:** Capacity Calculator
- **Parameters:** `maintenance_warning_ratio`, `warning_threshold_km`, `max_rotations_before_maintenance`
- **Refresh:** Daily
- **Severity:** High
- **Drill-down:** At-risk trucks
- **Required action:** Pre-empt maintenance or source trucks
- **Success criteria:** Capacity at risk within planned buffer
- **Failure impact:** Operational + Planning — surprise downtime mid-week
- **Depends on:** KPI-MNT-400, KPI-HSE-500
- **Lifecycle:** Calculated daily → consumed by KPI-OPS-002 and Intelligence (`FleetCapacityReduced`) → displayed in Fleet & Executive → drill to at-risk trucks → archived 13-month rolling.

### KPI-FLT-202 · Fleet Utilization
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
- **Depends on:** KPI-FLT-200, KPI-OPS-008
- **Lifecycle:** Calculated daily → consumed by Fleet review → displayed in Fleet → drill to per-truck utilization → archived 13-month rolling.

### KPI-FLT-203 · Truck Productivity
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
- **Depends on:** KPI-FLT-202
- **Lifecycle:** Calculated weekly → consumed by Fleet review → displayed in Fleet → drill to truck detail → archived 24-month rolling.

---

## 4. Dispatch (DSP)

### KPI-DSP-300 · Not-Started Planned Loads
- **Purpose:** catch planned trucks that have not started, while the day can still be saved.
- **Business question:** Which planned trucks have not started?
- **Business decision:** Whether to reassign or call the driver now.
- **Owner:** Dispatch
- **Data sources:** Dispatch Read Model
- **Calculator:** Dispatch Calculator
- **Parameters:** `start_deadline_hours`
- **Refresh:** Real-time (15 min)
- **Severity:** High
- **Drill-down:** Dispatch board
- **Required action:** Reassign or call the driver
- **Success criteria:** 0 planned trucks unstarted past the deadline
- **Failure impact:** Operational — lost rotations, day under plan
- **Depends on:** —
- **Lifecycle:** Calculated real-time → consumed by KPI-OPS-001 and Intelligence (`TruckUnavailable`) → displayed in Dispatch & Operations → drill to dispatch board → archived 90 days.

### KPI-DSP-301 · Dispatch Efficiency
- **Purpose:** show how plan converts to started and completed work.
- **Business question:** How much of the plan was started and completed?
- **Business decision:** Whether the planning assumptions need adjustment.
- **Owner:** Dispatch
- **Data sources:** Dispatch Read Model, Transport Tracking Read Model
- **Calculator:** Dispatch Calculator
- **Parameters:** `start_deadline_hours`
- **Refresh:** Hourly
- **Severity:** Medium
- **Drill-down:** Dispatch detail
- **Required action:** Adjust planning assumptions
- **Success criteria:** Planned → completed conversion within target
- **Failure impact:** Planning — recurring plan/execution gap
- **Depends on:** KPI-DSP-300
- **Lifecycle:** Calculated hourly → consumed by Dispatch review → displayed in Dispatch → drill to dispatch detail → archived 13-month rolling.

---

## 5. Maintenance (MNT)

### KPI-MNT-400 · Trucks At Breakdown Risk
- **Purpose:** name the trucks likely to stop production soon.
- **Business question:** Which trucks risk stopping production?
- **Business decision:** Whether to schedule maintenance before failure.
- **Owner:** Maintenance
- **Data sources:** Maintenance Read Model, Fleet Read Model
- **Calculator:** Maintenance Calculator
- **Parameters:** `maintenance_warning_ratio`, `warning_threshold_km`, `max_rotations_before_maintenance`
- **Refresh:** Daily
- **Severity:** Critical
- **Drill-down:** Maintenance-due list
- **Required action:** Schedule maintenance
- **Success criteria:** 0 trucks past the warning threshold unscheduled
- **Failure impact:** Operational + Safety — unplanned breakdown, lost capacity
- **Depends on:** —
- **Lifecycle:** Calculated daily → consumed by KPI-FLT-200, KPI-FLT-201 and Intelligence (`MaintenanceOverdue`) → displayed in Maintenance & Fleet → drill to due list → archived 24-month rolling.

### KPI-MNT-401 · Maintenance Due (Next 7 Days)
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
- **Depends on:** KPI-MNT-400
- **Lifecycle:** Calculated daily → consumed by Maintenance planning → displayed in Maintenance → drill to forecast → archived 13-month rolling.

---

## 6. Safety / HSE (HSE)

### KPI-HSE-500 · Trucks Legally Blocked
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
- **Lifecycle:** Calculated daily → consumed by KPI-FLT-200, KPI-FLT-201 and Intelligence (`InspectionExpired`) → displayed in HSE & Fleet → drill to blocked list → archived 24-month rolling.

### KPI-HSE-501 · Inspections Awaiting Validation
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
KPI-MNT-400 Trucks At Breakdown Risk ─┐
KPI-HSE-500 Trucks Legally Blocked  ──┼─► KPI-FLT-200 Operational Capacity Today ─► KPI-FLT-201 Capacity At Risk
KPI-DSP-300 Not-Started Planned Loads ┘                 │
                                                        ▼
KPI-OPS-008 Average Turnaround ─► KPI-FLT-202 Fleet Utilization        KPI-OPS-002 Capacity Gap
KPI-OPS-004 Missing Loads ─┐
KPI-OPS-005 Weight Discrepancy ─► KPI-FIN-100 Billing Readiness ─► KPI-FIN-101 Revenue Blocked
KPI-OPS-003 Production Pace ─► KPI-OPS-001 Objective Confidence ─► KPI-FIN-102 Revenue Forecast
```

## 8. KPI relationship (value chain)
```
Fleet Availability (FLT-200)
        │
        ▼
Capacity Gap (OPS-002)  ◄── Capacity At Risk (FLT-201)
        │
        ▼
Objective Confidence (OPS-001)
        │
        ▼
Revenue Forecast (FIN-102)
```

---

## 9. Weight threshold policy (three independent thresholds)
The platform keeps **three separate weight thresholds** because they answer three different business
questions. They are **never merged**. All three are owned by the **Weight Calculator** (one calculation
owner) but configured by independent parameters and consumed by different stakeholders.

| Threshold | Purpose | Default | Owner | Parameter key | Calculator | Consumers |
|---|---|---|---|---|---|---|
| **Operational** | Daily operational anomaly detection | 0.5 t | Operations | `weight_operational_threshold_t` | Weight Calculator | KPI-OPS-005, KPI-OPS-006, KPI-FIN-100; event `WeightAnomaly` |
| **Fraud** | Potential theft investigation | 300 kg | Executive / Audit | `weight_fraud_threshold_kg` | Weight Calculator | Audit review; fraud-level `WeightAnomaly` |
| **Technical** | Sensor / weighbridge validation | 150 kg | Fleet / Technical | `weight_sensor_threshold_kg` | Weight Calculator | Sensor validation; Fleet technical review |

### Retired KPI identifiers
*(none yet — recorded here when a KPI is retired; identifiers are never reused.)*

---

## 10. Rejected KPIs (intentionally removed)
| Removed metric | Why rejected | Constitution principle | Replaced by |
|---|---|---|---|
| Truck count | Vanity — a count, not an outcome | P14 outcomes-not-activity | KPI-FLT-200 Operational Capacity Today |
| Driver count | Vanity — headcount drives no decision | P14 | KPI-OPS-006 Driver Productivity |
| Raw rotation counts | Activity, not result | P14 | KPI-OPS-003 / KPI-OPS-001 |
| Saturation rate | No decision attached | P2 action-before-information | KPI-FLT-202 Fleet Utilization |
| Raw availability rate | Not actionable as a bare % | P4 intelligence-before-reporting | KPI-FLT-200 + KPI-FLT-201 |
| Fuel per rotation | No owner, no decision | P2 | Folded into KPI-FLT-203 Truck Productivity |
| Inspection counts (week/month) | Activity volume | P14 | KPI-HSE-500 Trucks Legally Blocked |
| Pending-checklists count | A status, not a decision | P11 operational-language | KPI-HSE-501 Inspections Awaiting Validation |
| Suspicious-drivers count | Vague label, no action | P5 context-matters | KPI-OPS-005 + KPI-OPS-006 |

---

## 11. Governance rules (mandatory)
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

## 12. KPI evolution policy (how a new KPI is added)
A KPI is added **only if** it:
- passes the [Feature Approval Gate](workspace-standard.md) (8 stages);
- conforms to the Constitution;
- has a **unique owner** department;
- has a **unique calculator**;
- drives a clear **operational decision**;
- delivers a **measurable business value**;
- receives the next free identifier **in its reserved range** (§0) — existing identifiers are never renumbered, retired ones never reused.

Otherwise it is rejected and recorded in §10. Changing an existing KPI's *meaning* requires a new identifier and an ADR.

---

## 13. Reserved for future (architecture only — not implemented)
These categories are reserved so later phases extend without renumbering. **No definitions exist yet.**
- **Predictive KPIs** (`PRD-700 → PRD-799`) — e.g. predicted breakdown date, predicted objective shortfall.
- **Optimization KPIs** (`OPT-800 → OPT-899`) — e.g. optimal reallocation, optimal dispatch sequencing.
- **AI-generated insights** (`AIX-900 → AIX-999`) — narrative conclusions beyond deterministic calculators.
- **Forecasting** — extends Finance/Operations forecasts (e.g. KPI-FIN-102) under their reserved ranges.

When implemented, each must still satisfy §11 and §12.

---

## 14. Architecture Decision Records
Permanent decisions. Status values: Proposed · Accepted · Superseded.

### ADR-001 — Default operational capacity = 45 tonnes
- **Status:** Accepted.
- **Previous behaviour:** several KPI calculations fell back to **25 t** when the fleet setting was empty.
- **Why it existed:** an early default before the fleet standardised on 45 t trucks.
- **Why replaced:** the canonical fleet capacity was standardised to **45 t**; the 25 t fallback was legacy technical debt that understated capacity and overstated utilization whenever the setting was unset.
- **Reason:** one source of truth; correctness; remove a conflicting magic value.
- **Impact:** `default_capacity_tonnage` is the single source; affects KPI-OPS-002, KPI-FLT-200, KPI-FLT-203 and any capacity/utilization KPI.
- **Migration impact:** when the fleet setting is populated (the normal case) **no number changes**; only the empty-setting edge case moves from 25 t to 45 t. Captured by characterization tests in R1.
- **Backward compatibility:** existing populated settings are unaffected; the value remains operator-configurable via the parameter.

### ADR-002 — Three independent weight thresholds (never merged)
- **Status:** Accepted.
- **Reason:** operational anomaly (0.5 t), fraud investigation (300 kg), and sensor validation (150 kg) answer different business questions with different owners; merging them would destroy meaning.
- **Impact:** three parameters (`weight_operational_threshold_t`, `weight_fraud_threshold_kg`, `weight_sensor_threshold_kg`), all owned by the Weight Calculator, distinct consumers (§9).

### ADR-003 — One calculator per KPI
- **Status:** Accepted.
- **Reason:** a calculation must have a single owner to stay trustworthy and changeable in one place.
- **Impact:** every KPI names exactly one Domain Calculator; duplicate calculations are removed in R1.

### ADR-004 — One owner department per KPI
- **Status:** Accepted.
- **Reason:** every problem needs exactly one accountable owner and one queue.
- **Impact:** each KPI names one owner; command centers may *consume* a KPI they do not own.

### ADR-005 — Read Models only (no direct database access above L0)
- **Status:** Accepted.
- **Reason:** calculators, events, intelligence, and dashboards must not couple to storage; data normalisation lives in one layer.
- **Impact:** all KPI data sources are Read Models; nothing above L0 queries storage.

### ADR-006 — No calculations in dashboards
- **Status:** Accepted.
- **Reason:** dashboards are presentation only; business logic in the UI breaks One Truth.
- **Impact:** command centers consume Operational Intelligence; they never compute KPIs, thresholds, or rules.

### ADR-007 — Dispatch Calculator is an official Domain Calculator
- **Status:** Accepted.
- **Reason:** dispatch is a distinct capability (dispatch readiness · planned-vs-started · assignment completeness · unassigned trucks · dispatch delay · execution) and must not reuse the Rotation/Capacity/Operations calculators.
- **Impact:** adds the Dispatch Calculator inside the existing Domain Calculators layer (owns KPI-DSP-300, KPI-DSP-301); no layer, boundary, or rule changes.

### ADR-008 — Operational Parameter Store
- **Status:** Accepted.
- **Why parameters exist:** every configurable operational value (threshold, capacity, SLA, fiscal day, maintenance limit) lives in one store — `operational_parameters` — resolved through `OperationalParameterService`. One value, one place, changeable without code.
- **Why defaults are NOT in code:** the service resolves stored values only; it never knows a business default. A missing parameter throws `MissingOperationalParameterException` rather than guessing. Defaults live in the **seeder** (current production values), the **migration history**, and these **ADRs** — so a value can never silently diverge between a code fallback and the store.
- **Keys / categories / units / owners are enums, never strings:** `OperationalParameterKey`, `ParameterCategory`, `ParameterUnit`, `ParameterOwner`. Calculators consume the key enum; no duplicated literals.
- **Cache strategy:** the active key→value map is loaded once and held in a process memo plus `Cache::rememberForever` — never two queries for one key. Any write (model save/delete or `service->set()`) forgets the cache key, so reads never go stale; cross-instance updates are visible on the next request.
- **Value column:** stays `text` paired with a `type` discriminator (`int|float|bool|string|json`). This stores scalars **and** json-encoded structures without a future migration per new type — reviewed and kept deliberately.
- **Ownership:** every parameter names one `owner` department (mirrors the KPI Catalog) and carries governance metadata (`editable`, `deprecated`, `introduced_by_adr`, `notes`).
- **Evolution rules:** add a parameter = add an `OperationalParameterKey` case + a validated seeder row (unique key · known category/unit/owner · value matches type — the seeder fails fast otherwise). Never reuse a retired key. Changing a *meaning* requires a new key + an ADR. New band/rate parameters with no current value are introduced with their KPI's calculator, never invented early.

### ADR-009 — FleetSetting is temporary compatibility storage
- **Status:** Accepted.
- **Context:** during R1.3 the seeded parameters were found to differ from live operator values (`default_capacity_tonnage` 41 vs seed 45; `monthly_target_tonnage` 3000 vs 0). The live source for these editable values was `FleetSetting`.
- **Decision:** `OperationalParameter` is the single source of truth; `FleetSetting` remains **only as compatibility storage** until R1.8. `FleetSettingsController` **dual-writes** both stores from one validated payload (no duplicated validation/logic, via `FleetSettingParameterMap`); `operations:sync-parameters` (idempotent, `--dry-run`) back-fills parameters from live `FleetSetting`.
- **Rules:** Domain Calculators read **parameters only, never `FleetSetting`**. R1.8 removes the remaining `FleetSetting` reads (KPI services, `TransportTracking::weightGapThreshold()`) one consumer at a time, each under characterization tests.
- **Impact:** zero behaviour change (parameters now mirror live values; dual-write keeps them identical).

---

## 15. Final validation
- **Can a new contributor build every command center from this document alone?** Yes — each KPI states its question, decision, owner, source, calculator, parameters, action, drill-down, and dependencies.
- **Can a business manager understand every KPI without reading code?** Yes — no implementation vocabulary; every KPI is phrased as a decision.
- **Can every KPI be traced to exactly one calculator?** Yes — one calculator per KPI, enforced by §11 and ADR-003.
