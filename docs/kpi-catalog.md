# KPI Catalog — Operational Intelligence Platform (R0.1)

> Authoritative catalog. **No KPI may exist outside this catalog.** Every entry is an *outcome*
> with one owner, one calculator (single source of truth), thresholds sourced from Operational
> Parameters, and a required action. Vanity metrics are removed; duplicates are merged.
> Conforms to the [Constitution](workspace-standard.md) and the [frozen architecture](operational-intelligence-architecture.md).
> Status: **proposed — awaiting approval** (R0.1). Calculators/Read Models/Parameters named here are built in R1.

## A. Removed — vanity metrics (Constitution P10/P14)
trucks count · drivers count · raw rotation counts · "saturation rate" · raw availability-rate · fuel-per-rotation · inspection counts (week/month) · pending-checklists count · suspicious-drivers count. *(Replaced by the outcomes below.)*

## B. Merged — one owner per calculation (Constitution P7)
weight-gap & violations → **WeightCalculator** · default-capacity → **CapacityCalculator** (+param) · cycle-days → **CycleCalculator** · discipline → **ProductivityCalculator** · fuel-yield → **FuelCalculator** · load-rate → **UtilizationCalculator** · tonnage/rotation aggregations → **RotationCalculator** · monthly-tonnage (22→21) → **RotationCalculator/FiscalCalendar** · maintenance-level → **MaintenanceCalculator** · availability → **UtilizationCalculator**.

## C. Catalog (each KPI — the 10 required fields)
Schema: **Name** · **Business question** · **Formula (owner calculator)** · **Source (read model)** · **Owner** · **Refresh** · **Thresholds (param)** · **Action** · **Drill-down** · **Command center**.

### Objective & Operations
1. **Objective confidence** — *Will we hit the period objective?* — `ObjectiveCalculator.confidence` (projection vs target, operational-day aware) — TransportTracking+FleetObjective RM — **Operations** — hourly — `objective_confidence_bands` — *Allocate reserve trucks / reallocate* — per-truck realization — Operations, Executive.
2. **Capacity gap (uncovered volume)** — *How much volume is uncovered vs objective?* — `CapacityCalculator.gap` — Fleet+Objective RM — **Operations** — hourly — — *Add/reallocate capacity* — capacity breakdown — Operations.
3. **Production pace today** — *Are we on pace today?* — `RotationCalculator.paceToday` — TransportTracking RM — **Operations** — 15 min — `pace_band` — *Push quarry / dispatch* — today's loads — Operations.

### Billing & Finance
4. **Billing readiness** — *What share of delivered tonnage is invoice-ready?* — `BillingCalculator.readiness` (complete+validated+documented) — TransportTracking RM — **Finance** — hourly — — *Complete tickets* — incomplete-tickets queue — Finance, Operations.
5. **Revenue blocked** — *How much revenue is stuck unbillable?* — `BillingCalculator.blockedValue` — TransportTracking RM — **Finance** — hourly — — *Complete docs/weights* — blocked-tickets queue — Finance, Executive.
6. **Missing loads (unticketed)** — *Which delivered loads have no ticket?* — `RotationCalculator.missingLoads` (GPS open, unlinked) — Dispatch+TransportTracking RM — **Operations** — nightly + on-write — `billable_window_days` — *Create ticket (seeded)* — missing-loads queue — Operations, Finance.
7. **Weight discrepancy exposure** — *Which loads show a weighing problem?* — `WeightCalculator.anomalies` (abs(gap) > threshold) — TransportTracking RM — **Operations** — on-write — `weight_gap_threshold_t` — *Verify weighbridge ticket* — anomaly list — Operations.

### Fleet & Capacity
8. **Operational capacity today** — *What capacity can run today?* — `CapacityCalculator.availableToday` — Fleet RM — **Fleet** — hourly — — *—* — fleet status — Fleet, Executive.
9. **Capacity at risk (this week)** — *How much capacity will we lose this week?* — `CapacityCalculator.atRisk` (down + due-maintenance + no-driver) — Fleet+Maintenance RM — **Fleet** — daily — `maintenance_warning_*` — *Pre-empt maintenance / source trucks* — at-risk trucks — Fleet, Executive.
10. **Fleet utilization** — *Are available trucks actually used?* — `UtilizationCalculator.fleet` — Fleet+TransportTracking RM — **Fleet** — daily — `utilization_band` — *Rebalance dispatch* — per-truck utilization — Fleet.

### Maintenance
11. **Trucks at breakdown risk** — *Which trucks risk stopping production?* — `MaintenanceCalculator.level` (red) — Maintenance+Fleet RM — **Maintenance** — daily — `maintenance_warning_pct`, `warning_threshold_km` — *Schedule maintenance* — due list — Maintenance, Fleet.
12. **Maintenance due (next 7 days)** — *What maintenance is coming?* — `MaintenanceCalculator.dueWithin(7)` — Maintenance RM — **Maintenance** — daily — `max_rotations_before_maintenance` — *Book workshop* — forecast — Maintenance.

### Dispatch
13. **Not-started planned loads** — *Which planned trucks haven't started?* — `DispatchCalculator.notStarted` (planned ∧ no GPS load by T) — Dispatch RM — **Dispatch** — 15 min — `start_deadline_hours` — *Reassign / call driver* — dispatch board — Dispatch, Operations.
14. **Dispatch efficiency** — *Planned vs started vs completed?* — `DispatchCalculator.efficiency` — Dispatch+TransportTracking RM — **Dispatch** — hourly — — *—* — dispatch detail — Dispatch.

### HSE
15. **Trucks legally blocked** — *Which trucks can't legally operate?* — `InspectionCalculator.blocked` (failed/expired) — Inspection+Fleet RM — **HSE** — daily — `inspection_sla_days` — *Validate / correct* — blocked list — HSE, Fleet.
16. **Inspections awaiting validation** — *What compliance is pending sign-off?* — `InspectionCalculator.unsigned` — Inspection RM — **HSE** — daily — — *Validate* — validation queue — HSE.

### Performance (each ends in an action)
17. **Driver productivity** — *Who needs coaching or reassignment?* — `ProductivityCalculator.driver` — TransportTracking+Discipline RM — **Operations** — weekly — `productivity_band` — *Coach / reassign* — driver detail — Operations.
18. **Truck productivity** — *Which trucks underperform?* — `ProductivityCalculator.truck` — TransportTracking RM — **Fleet** — weekly — — *Investigate* — truck detail — Fleet.
19. **Provider (quarry) performance** — *Which quarry slows loading?* — `ProductivityCalculator.provider` (turnaround/throughput) — Dispatch+GPS RM — **Operations** — daily — — *Call provider* — provider detail — Operations.
20. **Average turnaround** — *Which lane/route is slow?* — `CycleCalculator.turnaround` — TransportTracking RM — **Operations** — daily — — *Investigate bottleneck* — route detail — Operations.

## D. Notes
- Conclusions (e.g. "Objective at risk · missing 220 t · cause: 2 trucks down") are produced by the **Operational Intelligence** layer from these KPIs + Business Events (R1.6) — the catalog defines the measured outcomes; intelligence narrates them.
- Thresholds reference **Operational Parameters** keys; flagged conflicts (capacity 25/45; weight-gap 0.5t/300kg/150kg) are decided by the business before they are consumed.
- Every entry passes the Five-Question Test (what/why/what-do-I-do/who-owns/impact-if-ignored).
