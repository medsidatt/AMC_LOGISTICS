# ADR — Deferred Canonical Business Events (R3.x)

> **Status: DEFERRED (2026-07-01).** Decision: freeze the Operational Intelligence event pipeline
> at **5 of 8** canonical events. Three events are formally deferred because they depend on
> explicit business rules / operational parameters (and, in two cases, Read Model data) that do
> **not** exist in the system. Per the frozen-architecture rules we do **not** invent business
> rules, guess thresholds, or add provisional parameters. No placeholder logic ships.

## Context

The pipeline (Read Models → Calculators → Business Event Derivers → `DerivedBusinessEventSource`
→ Operational Intelligence → Dashboard Translators → Command Centers → React) is generic and
complete. The Source, Intelligence, Translators, and Command Centers already handle all 8
canonical events; the blockage is **only at the producer end** (Read Model / Calculator /
Deriver), and only for the three events below.

**Implemented & wired (5):** `MaintenanceOverdue` (km-tracked), `InspectionExpired`,
`WeightAnomalyDetected`, `MissingTransportTicket`, `TruckUnavailable`.

## Deferred events

### 1. `BillingBlocked` — KPI-FIN-100 (Finance)
- **Missing business rule:** the definition of *invoice-ready* — which delivered tonnage is
  billable vs blocked (e.g. `is_validated` AND all required documents attached AND inside the
  billing window).
- **Missing operational parameters:** `billable_window_days` (unprovisioned — noted in
  KPI-FIN-100); `revenue_rate_per_tonne` (no such `OperationalParameterKey` exists) for
  `BillingCalculator::blockedRevenue`.
- **Missing calculator capability:** a "which tickets/tonnage are blocking" decision.
  `BillingCalculator` already owns the arithmetic (`readinessRate`, `blockedRevenue`) but has no
  inputs to decide on.
- **Missing Read Model capability:** a billing-readiness projection over `transport_tracking`
  (ready vs total tonnage, documents-complete status). None exists (R3.0 gate deferred it).
- **Why blocked:** requires a business-defined readiness rule + two unprovisioned parameters +
  new Read Model data. All are "do-not-invent" items.
- **Future entry point:** add `TransportTrackingReadModel::billingReadiness(from,to)` (raw ready/
  total tonnage) → `BillingCalculator` blocking decision (using the new params) → a
  `BillingEventDeriver` emitting `BillingBlocked`. Bind the deriver in the source's stable list.

### 2. `ObjectiveBehindSchedule` — KPI-OPS-001 (Operations)
- **Missing business rule:** how "behind **schedule**" is decided — the pace / confidence band
  (projected end-of-period vs target). Deciding "behind" without it means inventing proration.
- **Missing operational parameters:** `objective_confidence_bands` (unprovisioned — noted in
  KPI-OPS-001).
- **Missing calculator capability:** `ObjectiveCalculator` has `achievement/deficit/…` (all take
  `actual, target`) but no `target(...)` resolution and no "behind schedule" confidence decision.
- **Missing Read Model capability:** none — `actual` is available via
  `TransportTrackingReadModel::periodTotals`; `target` can come from `MONTHLY_TARGET_TONNAGE`
  (exists) or trucks × capacity × rotations.
- **Why blocked:** the decision rule (confidence band) is an unprovisioned parameter; mid-period
  `actual < target` is trivially true, so a faithful decision needs the band.
- **Future entry point:** provision `objective_confidence_bands` → add
  `ObjectiveCalculator::target(...)` + `isBehindSchedule(actual, target, asOf, period)` (band-
  based) → an `ObjectiveEventDeriver` composing `periodTotals` + `FleetReadModel` capacity.

### 3. `CapacityReduced` — KPI-FLT-201 (Fleet)
- **Missing business rule:** how "capacity lost **this week**" is decided — a *time-to-maintenance*
  rule (converting km-remaining to a due-within-the-week signal) or a maintenance-due-this-week
  definition. The existing warning-band params are km-based, not time-based.
- **Missing operational parameters:** none new strictly required (`MAINTENANCE_WARNING_RATIO`,
  `WARNING_THRESHOLD_KM`, `MAX_ROTATIONS_BEFORE_MAINTENANCE`, `CAPACITY_BUFFER_RATIO` all exist) —
  but the *rule* tying them to "this week" is unspecified.
- **Missing calculator capability:** a capacity-at-risk decision (at-risk capacity vs buffer);
  `CapacityCalculator` owns only `defaultCapacity/truckCapacity`.
- **Missing Read Model capability:** a maintenance-due-this-week (or rotations-since-service)
  projection to identify at-risk trucks by time. `MaintenanceReadModel` exposes raw km/date only.
- **Why blocked:** the temporal "this week" rule and the at-risk aggregation are unspecified
  business logic; approximating with the km warning band would silently change the KPI's meaning.
- **Future entry point:** define the time-to-maintenance rule → add a maintenance-due-this-week
  Read Model projection → `CapacityCalculator::capacityAtRisk(...)` / `isReducedBeyondBuffer(...)`
  → a `CapacityEventDeriver` composing `FleetReadModel` + the maintenance projection.

## Remaining business decisions required from stakeholders

1. **Objective confidence/pace band** (values + shape) for `objective_confidence_bands`.
2. **Billing-readiness definition** + `billable_window_days` + `revenue_rate_per_tonne`.
3. **Capacity-at-risk / time-to-maintenance rule** (what "lost this week" means operationally).

Until these are provided, the three events stay deferred. No provisional thresholds, no guessed
formulas, and no TODOs in production code — this document is the single record of the deferral.
