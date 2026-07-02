# KPI Registry (R1.5) — Implementation Notes

> Layer L4 of the [frozen architecture](operational-intelligence-architecture.md).
> The Registry is the single authoritative source of **KPI metadata**. It encodes the
> [KPI Catalog](kpi-catalog.md) in code — one definition per KPI, nothing more.
> The catalog remains the business contract; this layer is its typed, queryable mirror.

## What it is — and is not

The Registry **describes** KPIs. It never calculates, never queries, never reads
Eloquent, never reads a parameter value, never resolves a service, never instantiates
an event, never touches `config()`/`env()`, and never depends on the UI or dashboards.
Every definition is an immutable (`final readonly`) value object built from hardcoded
catalog metadata and held in a process memo.

It points at the components that do the work, by **stable identity only**:
calculator *interface* (FQN), Read Models (`KpiDataSource`), Operational Parameters
(`OperationalParameterKey`), and Business Events (`EventId`, reference only — R1.4 is
unconsumed).

## Files

```
app/Domain/Operations/KPI/
├── Contracts/
│   └── KpiDefinitionInterface.php     accessor contract for one KPI's metadata
├── Enums/
│   ├── KpiId.php                      21 stable ids (KPI-<DOMAIN>-NNN)
│   ├── KpiCategory.php                OPS/FIN/FLT/DSP/MNT/HSE (+ reserved-range prefix)
│   ├── KpiOwner.php                   one accountable department per KPI (ADR-004)
│   ├── KpiSeverity.php                critical → informational
│   ├── KpiRefreshStrategy.php         realtime/hourly/daily/weekly/monthly
│   ├── KpiUnit.php                    percent/tonnes/count/currency/hours/days/ratio
│   ├── KpiDataSource.php              the six Read Models (2 implemented, contract() resolves them)
│   └── CommandCenter.php              display destinations (incl. Executive consumer view)
├── KpiDefinition.php                  immutable VO implementing the contract
└── KpiRegistry.php                    builds + serves the definitions
```

## API

```php
$registry = new KpiRegistry;

$registry->find(KpiId::OPS_001);             // one definition (throws on a gap)
$registry->all();                            // every KPI, catalog order
$registry->active();                         // non-deprecated
$registry->critical();                       // severity === CRITICAL
$registry->byOwner();                        // grouped map owner => [definitions]
$registry->byCategory();                     // grouped map category => [definitions]
$registry->byDashboard();                    // grouped by each command center placement

// filters
$registry->ownedBy(KpiOwner::FINANCE);
$registry->inCategory(KpiCategory::OPERATIONS);
$registry->inCommandCenter(CommandCenter::EXECUTIVE);
$registry->bySeverity(KpiSeverity::HIGH);
$registry->has(KpiId::FLT_200);
```

## Registered KPIs (21)

| Category | KPIs |
|---|---|
| Operations (8) | OPS-001 Objective Confidence · OPS-002 Capacity Gap · OPS-003 Production Pace · OPS-004 Missing Loads · OPS-005 Weight Discrepancy · OPS-006 Driver Productivity · OPS-007 Provider Performance · OPS-008 Average Turnaround |
| Finance (3) | FIN-100 Billing Readiness · FIN-101 Revenue Blocked · FIN-102 Revenue Forecast |
| Fleet (4) | FLT-200 Operational Capacity Today · FLT-201 Capacity At Risk · FLT-202 Fleet Utilization · FLT-203 Truck Productivity |
| Dispatch (2) | DSP-300 Not-Started Planned Loads · DSP-301 Dispatch Efficiency |
| Maintenance (2) | MNT-400 Trucks At Breakdown Risk · MNT-401 Maintenance Due (7 Days) |
| HSE (2) | HSE-500 Trucks Legally Blocked · HSE-501 Inspections Awaiting Validation |

## Parameter references & ADR-008

Definitions reference only **existing** `OperationalParameterKey` cases. Catalog
parameters that are not yet provisioned — the confidence/pace/productivity/utilization
**bands**, `billable_window_days`, `revenue_rate_per_tonne`, `start_deadline_hours` —
are introduced with their KPI's calculator in a later increment (ADR-008: bands/rates
are never invented early). Until then each affected KPI records the future key in its
`notes`. No new parameter, migration, or seeder row was added in R1.5.

## Identity alignment (R1.5.1)

The three identity mismatches the R1.5 gate flagged are **resolved** — the platform now
has one canonical name per concept:

- **Business event:** `BillingBlocked` is the single finance-blocking event (catalog +
  frozen architecture); the duplicate `RevenueBlocked` event was removed
  (`EventId::REVENUE_BLOCKED` deleted). Its sole emitter is KPI-FIN-100 (see
  `byEvent()` and the R1.6.1 hardening); FIN-101 consumes it via its dependency on FIN-100.
- **Calculator:** `BillingCalculator` / `BillingCalculatorInterface` (matches the frozen
  architecture L2 list and the catalog). The R1.3 `FinanceCalculator` name was retired.
- **Parameter:** `maintenance_warning_ratio` everywhere (matches the stored 0.1 ratio);
  the catalog text `maintenance_warning_pct` was corrected.

## Carried-forward technical debt (mandatory before R1.6)

These are **not** implemented here; they are tracked for the R1.6 gate.

- **Event Derivers** — wire the R1.4 events to calculators (still unconsumed).
- **Event payload refinement** — align deriver payloads when the events are consumed.
- **Legacy `DailyDispatchEventDeriver` boundary** — reconcile with the Events layer.
- **Future band/rate parameters** — provision with their calculators (ADR-008).
- **Remaining Read Models** — Dispatch / Maintenance / Inspection / Fuel (R1.2 set);
  `KpiDataSource::contract()` returns `null` for these until implemented.
