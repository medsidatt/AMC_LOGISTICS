# Operational Intelligence (R1.6) — Implementation Notes

> Layer L5 of the [frozen architecture](operational-intelligence-architecture.md).
> The company's **decision engine**. It transforms Business Events (facts) + KPI Registry
> (metadata) into **Operational Conclusions** — what requires attention, why, who owns it,
> what to do, and how urgent. It answers decisions; it never answers with formulas.

## What it is — and is not

Operational Intelligence is a **pure transform**: `events in → conclusions out`, in input
order. Given the same events it always produces the same conclusions. It **consumes Business
Events + the KPI Registry only** (frozen L5). It never:

- calculates a business formula · queries Eloquent or SQL · reads `config()`/`env()`/`FleetSetting`
- instantiates a calculator or read model · emits events / touches the bus
- **chooses between KPIs** · **filters or sorts** for presentation
- contains UI, presentation, translation, colours, charts, widgets, controllers, routes, or HTTP

Events arrive **already derived** (by an upstream deriver — *out of scope here*, carried as
debt). The engine does not produce events; it interprets them. Filtering, grouping, and
ordering for display belong to the Dashboard Translators (R1.7).

## Files

```
app/Domain/Operations/Intelligence/
├── Contracts/
│   └── OperationalIntelligenceInterface.php   the engine contract
├── OperationalIntelligence.php                the engine (final)
├── OperationalConclusion.php                  the decision record (15 fields)
├── OperationalFinding.php                     diagnosis: event ↔ KPI ↔ owner
├── OperationalRecommendation.php              prescription: decision · action · drill-down
├── OperationalEvidence.php                    proof: entity + pre-computed payload
└── OperationalPriority.php                    urgency rank (ordering, not calculation)
```

All value objects are `final readonly`.

## API

```php
$intelligence = app(OperationalIntelligenceInterface::class);

$intelligence->conclude($events);   // list<OperationalConclusion>, one per mapped event, input order
```

The engine exposes a single transform. Owner/command-center/severity **filtering is not here** —
it is presentation and belongs to the Dashboard Translators (R1.7).

## How a conclusion is built

For each input event, the engine asks the **KPI Registry** which KPI emits it
(`$registry->byEvent($event->id())`) and synthesises a conclusion. The **event** supplies the
fact (severity · business impact · affected entity · evidence · timestamp); the **KPI** supplies
the meaning (owner · decision · required action · drill-down · business question). Nothing is
computed and nothing is chosen.

### Event → KPI mapping lives in the Registry (one event, one emitter)

The Registry owns the mapping: each Business Event is emitted by exactly **one** KPI; a KPI that
merely consumes the same fact relates to the emitter through `dependencies()`, never by also
emitting (a second emitter fails fast in `KpiRegistry::byEvent()`). So:

- `CapacityReduced` → **FLT-201** (Fleet); OPS-002 consumes it via its dependency on FLT-201.
- `BillingBlocked` → **FIN-100 Billing Readiness** (Finance); FIN-101 consumes it via its dependency on FIN-100.

The engine **never decides** — it looks up. Ownership is **verified, not chosen**: if a fact's
owner disagrees with its KPI's owner, the engine throws `OwnershipMismatchException` (ADR-004,
fail-fast). Events with no emitting KPI carry no documented decision and yield no conclusion.

## The 15 conclusion fields

| # | Field | Source |
|---|---|---|
| 1 | id | `event:entityType:entityId` (deterministic) |
| 2 | business event | event id |
| 3 | related KPI | the single emitting KPI (`Registry::byEvent`) |
| 4 | severity | event |
| 5 | business impact | event |
| 6 | owner | KPI (= event owner) |
| 7 | decision | KPI |
| 8 | required action | KPI |
| 9 | explanation | KPI name + evidence summary + action |
| 10 | affected entity | event |
| 11 | drill-down target | KPI |
| 12 | timestamp | event |
| 13 | evidence | event payload |
| 14 | priority | severity → rank (1 = most urgent) |

## Example

`BillingBlocked` on `invoice_batch #7`, payload `{count: 14, subject: "incomplete transport tickets"}`
→ **"Billing Readiness — 14 incomplete transport tickets. Action: Complete the tickets / documents."**
(owner Finance · severity High · drill-down "Incomplete-tickets queue").

## Carried-forward technical debt (before R1.7)

- **Event Derivers** — the producers that compute Business Events from Calculators/Read Models
  are still not built; the engine is tested with constructed event fixtures.
- **Event payload conventions** — the evidence keys (`summary`, `count`, `subject`) are a light
  convention the derivers must honour when they are written.
- **Remaining Read Models** — Dispatch / Maintenance / Inspection / Fuel (R1.2 tail), needed by
  the future derivers, not by Intelligence itself.

R1.7 Dashboard Translators consume these conclusions; **no dashboard/UI work is done here.**
