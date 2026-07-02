# Dashboard Migration Inventory (Planning Milestone)

> **Type:** Planning/verification only — no code modified, nothing migrated. Becomes the **official
> roadmap**; every subsequent dashboard migration must follow it. **Sources of truth:**
> `docs/repository-knowledge-graph.md` + `docs/enterprise-architecture-audit.md` (both frozen), with
> targeted first-hand verification of controllers/services (HomeController, DashboardDataService,
> FleetKpiService, TrackingDashboardController). Governing rule: a legacy metric can migrate **only** if
> an existing Provider / Command Center / Read Model already owns it — surfacing an unowned metric would
> require a new owner (new work), which is forbidden this phase. Generated 2026-07-02.

---

## 1. Repository Audit — every dashboard/analytics/overview surface

Located by tracing route files → controllers → `Inertia::render` targets (never in isolation).

| # | Surface | Route | Controller → Page | Pipeline today |
|---|---|---|---|---|
| D1 | **Admin Home Dashboard** | `/dashboard` | `HomeController@index` → `Dashboard` | **LEGACY** (DashboardDataService + FleetKpiService) |
| D2 | **Driver Dashboard** | `/dashboard` (role-routed) | `HomeController@index` → `DriverDashboard` | **LEGACY** (DashboardDataService::getDriverData) |
| D3 | **HSE Dashboard** | `/dashboard` (role-routed) | `HomeController@index` → `HseDashboard` | **LEGACY** (DashboardDataService::getHseData) |
| D4 | **LogRes Dashboard** | `/dashboard` (role-routed) | `HomeController@index` → `LogisticsResponsibleDashboard` | **LEGACY** (DashboardDataService::getLogisticsResponsibleData) |
| D5 | **Transport Reports** | `/transport_tracking/dashboard` | `TrackingDashboardController@index` → `transport-trackings/Reports` | **LEGACY** (inline queries + fiscal-month dup [A3]) |
| D6 | **Analytics · Fleeti** | `analytics/fleeti` | `TrackingDashboardController@fleeti` → `analytics/Fleeti` | **LEGACY** (inline queries) |
| D7 | **Analytics · Rotations** | `analytics/rotations` | `TrackingDashboardController@rotations` → `analytics/Rotations` | **LEGACY** (inline + fiscal-month dup [A3]) |
| D8 | **Maintenance workspace (board)** | `/maintenance` | `MaintenanceController@index` → `maintenance/Index` | **PARTIAL** (board status via MaintenanceStatusService/MaintenanceCalculator; workspace not a KPI dashboard) |
| M1 | Executive Command Center | `/dashboard/executive` | `ExecutiveDashboardController` → `executive/Index` | **ALREADY MIGRATED** (frozen L3→L6 pipeline) |
| M2 | Operations Command Center | `/dashboard/operations` | `OperationsDashboardController` → `operations/CommandCenter` | **ALREADY MIGRATED** (frozen pipeline) |
| M3 | BI Executive | `/business/executive` | `BusinessDashboardController@executive` → `business/executive/Index` | **ALREADY MIGRATED** (BusinessKpiRegistry pipeline) |
| M4 | BI Operations | `/business/operations` | `BusinessDashboardController@operations` → `business/operations/Index` | **ALREADY MIGRATED** |
| M5 | BI Fleet | `/business/fleet` | `BusinessDashboardController@fleet` → `business/fleet/Index` | **ALREADY MIGRATED** |
| M6 | Fuel analytics (descriptive) | `/fuel/analytics` | `FuelImportController@analytics` → JSON | **ALREADY MIGRATED** (FuelDashboardDataProvider) |

**Not-a-dashboard / does not exist (from the example list):**
- **Theft Dashboard** — **does not exist.** Presentation removed in GPS Decoupling Phase 1A;
  `theft_incidents` is ACTIVE-WRITER / UI-ORPHANED (knowledge-graph §7/§10). No page to migrate.
- **Fleet / Logistics / KPI / Monitoring "pages"** as standalone dashboards — subsumed by the BI Fleet
  dashboard (M5), the Operations pages, and the command centers. No separate surfaces found.

**Migration targets = D1–D8** (M1–M6 already on the new architecture).

---

## 2. Dashboard Inventory (target surfaces, with what each displays)

**D1 Admin Home** — `FleetKpiService::compute()` → `kpis{availability, saturation, production_target,
load_rate, rotations, fuel_yield}`, `topTrucks[]`, `topDrivers[]`; `DashboardDataService::getAdminData()`
→ trucksCount, driversCount, tripsToday, tonnageMonth, recentTrackings[], maintenance-due, monthly
tonnage+utilization chart, `fleetCapacity{active_trucks, target rates, utilization_pct, top_trucks[],
bottom_trucks[]}`.

**D2 Driver** — personal trips, assigned truck, weekly-checklist status, reported issues (role-scoped).

**D3 HSE** — inspection-centric counts/lists (role-scoped).

**D4 LogRes** — operations/planning-centric lists (role-scoped).

**D5 Transport Reports** — tonnage (fiscal-month), gap exposure, trips/rotations, per-truck breakdown.

**D6 Analytics·Fleeti** — Fleeti telemetry rollups (km, consumption, refuel).

**D7 Analytics·Rotations** — rotation counts by fiscal month/truck.

**D8 Maintenance board** — per-truck red/yellow/green status, overdue counts.

---

## 3. Dependency Graph (target dashboards)

```
D1 Admin Home ──┬─ FleetKpiService(LEGACY) ─ availability/saturation/loadRate/rotations/tonnage
                │      ↳ owned in NEW arch by → FleetMetricsCalc / OperationsMetricsCalc / ProductivityMetricsCalc
                │        (via Fleet/OperationsBusinessCommandCenter ← BusinessKpiRegistry)
                │        ← FleetReadModel / TransportTrackingReadModel ← trucks / transport_trackings
                │        write owners: TruckController / TransportTrackingController(+import)
                ├─ production_target ─ owner = BI OPS_051 (RESERVED, no definition) ........ BLOCKED
                ├─ fuel_yield ─ owner = FuelCalculator (DORMANT, no Provider) .............. BLOCKED
                ├─ topTrucks/topDrivers RANKING ─ no calculator/provider owns ranking ...... BLOCKED
                └─ per-driver discipline ─ formula owned by ProductivityCalculator,
                                            but no descriptive Provider surfaces it ......... BLOCKED

D5/D7 Transport & Rotations ─ OperationsBusinessCommandCenter ← OperationsMetricsCalculator
                              (OPS_001-005: tonnage/trips/rotations/gap) ← TransportTrackingReadModel
                              (owns fiscal-month grouping) ← transport_trackings
                              write owner: TransportTrackingController (+ import)

D6 Analytics·Fleeti ─ FleetiConsumptionReadModel (via FuelDashboardDataProvider pattern)
                      ← fleeti_daily_records ← FleetiImportService (write owner)

D8 Maintenance board ─ MaintenanceCalculator + MaintenanceStatusService ← MaintenanceReadModel
                       ← maintenances/trucks ← MaintenanceController/TruckMaintenanceService

D2/D3/D4 Role dashboards ─ DashboardDataService(LEGACY) + DriverKpiService(LEGACY)
                           ↳ discipline/cycle formulas owned by ProductivityCalculator/CycleCalculator,
                             but NO per-driver/per-role descriptive Provider exists ......... PARTIAL/BLOCKED
```

---

## 4. Metric Ownership Matrix (does an existing owner surface it?)

| Metric (as displayed) | Current impl | Existing NEW-arch owner | Migratable now? |
|---|---|---|---|
| Fleet size / active trucks | DashboardDataService inline | **FleetMetricsCalculator FLT_001** (FleetReadModel) | ✅ YES |
| Available capacity | DashboardDataService inline | **FleetMetricsCalculator FLT_002** | ✅ YES |
| Availability rate | FleetKpiService `availability` | **FleetMetricsCalculator FLT_003** | ✅ YES |
| Saturation rate | FleetKpiService `saturation` | **FleetMetricsCalculator FLT_004** | ✅ YES |
| Monthly / period tonnage | DashboardDataService + FleetKpiService | **OperationsMetricsCalculator OPS_001/002** (TransportTrackingReadModel) | ✅ YES |
| Trips | inline `tripsToday` | **OperationsMetricsCalculator OPS_003** | ✅ YES |
| Rotations | FleetKpiService `rotations` | **OperationsMetricsCalculator OPS_004** | ✅ YES |
| Weight-gap exposure | TrackingDashboardController inline | **OperationsMetricsCalculator OPS_005** | ✅ YES |
| Load rate / fleet utilization | FleetKpiService `load_rate` (inline ×2) | **ProductivityMetricsCalculator PRD_001** (UtilizationCalculator) | ✅ YES |
| Fiscal-month grouping (22nd) | controller/service inline (dup [A3]) | **TransportTrackingReadModel** (owns it) | ✅ YES (removes dup) |
| Fleeti consumption (km/L/refuel) | TrackingDashboardController inline | **FleetiConsumptionReadModel** | ✅ YES |
| Maintenance red/yellow/green | MaintenanceStatusService | **MaintenanceCalculator** (MaintenanceReadModel) | ✅ YES (already calculator-backed) |
| Production-target % | FleetKpiService `production_target` | BI **OPS_051 RESERVED** (needs objective target rule) | ❌ BLOCKED |
| Fuel yield (L/tonne) | FleetKpiService `fuel_yield` | **FuelCalculator DORMANT**, no Provider (fuel KPI catalog frozen) | ❌ BLOCKED |
| Top/Bottom trucks (ranking) | DashboardDataService/FleetKpiService inline | **no owner** — no calculator/provider owns ranking | ❌ BLOCKED |
| Top drivers (ranking) | FleetKpiService::topDrivers (N+1 [P1]) | **no owner** | ❌ BLOCKED |
| Per-driver discipline score | DriverKpiService inline (formula = ProductivityCalculator) | formula owned; **no descriptive Provider** surfaces per-driver | ❌ BLOCKED |
| Recent trackings list | DashboardDataService raw query | TransportTrackingReadModel *could* project; **no current descriptive Provider method** | ⚠️ PARTIAL (raw list, not a KPI) |

**Legend:** ✅ an existing Provider/Command-Center/Read-Model already owns it → migratable under the rules.
❌ owner does not exist / is dormant / is reserved → surfacing it = new work (forbidden this phase).

---

## 5. Dashboard Readiness Report

| Dashboard | Classification | Why |
|---|---|---|
| **D5 Transport Reports** | **READY** | Every displayed metric (tonnage/trips/rotations/gap, fiscal-month) is owned by OperationsMetricsCalculator + TransportTrackingReadModel and already exposed via OperationsBusinessCommandCenter. Migration = point the page at the existing command center; removes controller-inline queries + fiscal-month dup [A3]. |
| **D7 Analytics·Rotations** | **READY** | Rotations/tonnage owned by OperationsMetricsCalculator (OPS_004/001); fiscal-month owned by read model. Same pattern as D5. |
| **D6 Analytics·Fleeti** | **READY** | Fleeti consumption owned by FleetiConsumptionReadModel (descriptive projections already built + tested). |
| **D8 Maintenance board** | **PARTIALLY READY** | Status already MaintenanceCalculator-backed; but it is an operational workspace (board/history/rules), not a pure dashboard — migrating "display" is low-value and risks touching CRUD. Treat as low priority. |
| **D1 Admin Home** | **PARTIALLY READY** | Headline magnitudes (fleet size, availability, saturation, tonnage, trips, rotations, load rate) are READY via Fleet/Operations/Productivity metrics calculators. BLOCKED widgets: production-target % (OPS_051 reserved), fuel yield (FuelCalculator dormant / fuel-KPI catalog frozen), top/bottom-truck + top-driver rankings (no ranking owner), per-driver discipline (no descriptive provider). |
| **D2 Driver** | **BLOCKED** | Role-scoped personal view; its KPIs come from DriverKpiService (legacy); no per-driver descriptive Provider exists. Mostly operational lists, not descriptive KPIs. |
| **D3 HSE** | **PARTIALLY READY / BLOCKED** | Inspection counts could map to InspectionReadModel/InspectionCalculator, but no descriptive HSE Provider surfaces them today (BI HSE_001 reserved). |
| **D4 LogRes** | **BLOCKED** | Operations/planning lists; no single descriptive Provider owns them; overlaps the Operations Command Center already migrated. |

---

## 6. Migration Order (dependency-driven — earliest unblocks/validates later)

1. **D5 Transport Reports** — *first.* Fully READY, zero blockers, and it establishes the canonical
   migration pattern: **legacy controller → existing BusinessCommandCenter/Provider, React becomes
   presentation-only.** Proves the pattern on the lowest-risk surface and retires architecture-violation
   [A3] (fiscal-month duplication) as a side effect of correct wiring (not as cleanup).
2. **D7 Analytics·Rotations** — *second.* Same command center (OperationsBusinessCommandCenter), same
   read model, same fiscal-month owner as D5 → D5 de-risks it entirely. Reuses D5's dashboard components.
3. **D6 Analytics·Fleeti** — *third.* READY via FleetiConsumptionReadModel; independent data source but
   same page pattern established by D5/D7.
4. **D1 Admin Home** — *fourth (partial).* Migrate only the READY headline set (fleet/ops/productivity
   magnitudes) by consuming the Fleet + Operations BI command centers; explicitly **defer** the BLOCKED
   widgets (production-target %, fuel yield, rankings, discipline) — they await owners that this phase may
   not create. Positioned last among the ready set because it is partial and highest-surface-area.
5. **D8 Maintenance board / D2 Driver / D3 HSE / D4 LogRes** — *not scheduled this phase.* Blocked or
   operational-workspace surfaces needing owners that don't exist yet (would be new KPIs/providers).

Ordering rationale is purely dependency completeness: D5 has the most complete existing ownership, and
each later item reuses the owners/pattern proven by an earlier one. Importance/UI/preference were not used.

---

## 7. Feasibility Matrix

| Dashboard | Deps Complete | Provider Ready | Read Model Ready | Calculator Ready | Business Rules Frozen | Migration Ready | Risk |
|---|---|---|---|---|---|---|---|
| D5 Transport Reports | ✅ | ✅ OperationsBusinessCC | ✅ TransportTrackingRM | ✅ OperationsMetricsCalc | ✅ | **YES** | LOW |
| D7 Analytics·Rotations | ✅ | ✅ OperationsBusinessCC | ✅ TransportTrackingRM | ✅ OperationsMetricsCalc | ✅ | **YES** | LOW |
| D6 Analytics·Fleeti | ✅ | ✅ (FuelDashboard pattern) | ✅ FleetiConsumptionRM | n/a (descriptive) | ✅ | **YES** | LOW |
| D1 Admin Home (ready set) | ⚠️ partial | ✅ Fleet+OperationsBusinessCC | ✅ Fleet/TransportTrackingRM | ✅ Fleet/Ops/ProductivityMetricsCalc | ⚠️ partial | **PARTIAL** | MEDIUM |
| D1 Admin Home (blocked widgets) | ❌ | ❌ none | n/a | ❌ FuelCalc dormant | ❌ OPS_051 reserved | **NO** | — |
| D8 Maintenance board | ⚠️ | ⚠️ no descriptive provider | ✅ MaintenanceRM | ✅ MaintenanceCalc | ✅ | **PARTIAL** | MEDIUM (CRUD workspace) |
| D3 HSE | ⚠️ | ❌ HSE_001 reserved | ✅ InspectionRM | ✅ InspectionCalc | ⚠️ | **NO** | MEDIUM |
| D2 Driver | ❌ | ❌ none | ⚠️ | ⚠️ (formulas only) | ⚠️ | **NO** | HIGH |
| D4 LogRes | ❌ | ❌ none | ⚠️ | ⚠️ | ⚠️ | **NO** | HIGH |

---

## 8. Risks

- **R1 — Blocked-widget pressure on D1 (MEDIUM).** The admin dashboard visibly loses top-N rankings,
  fuel yield, production-target % if migrated strictly. Risk = temptation to "just compute it in the
  provider," which would **invent an owner** (forbidden). Mitigation: migrate only the owned set; leave
  blocked widgets on the legacy path or hidden until the Repository Cleanup / future KPI phase.
- **R2 — Fiscal-month semantics drift (LOW).** D5/D7 must consume `TransportTrackingReadModel`'s owned
  22nd-cycle grouping, not re-implement it. Risk if a migrator copies the controller's inline `CASE WHEN
  DAY()>=22`. Mitigation: forbid the controller grouping; assert equivalence in tests.
- **R3 — Duplicate query reintroduction (LOW).** React must call one endpoint per dashboard (the command
  center / provider), not fan out. Mitigation: single provider call per page.
- **R4 — Touching the legacy KPI trio (MEDIUM).** D1 shares `FleetKpiService`/`DashboardDataService` with
  other consumers (truck/driver pages). Migration must **not** edit those services (that's the R1.3
  cleanup, a later phase) — it must add a new presentation path consuming existing command centers and
  leave the legacy service intact until cleanup. Mitigation: additive wiring only.
- **R5 — Maintenance/Role surfaces are workspaces, not dashboards (MEDIUM/HIGH).** Migrating them risks
  refactoring CRUD/operational logic (forbidden). Mitigation: exclude from this phase.

---

## 9. Remaining Gaps (owners that do not yet exist — block full migration)

| Gap | Blocks | Nature |
|---|---|---|
| No descriptive Provider owns **top-N truck/driver ranking** | D1 rankings | ranking not owned by any calculator (audit: "no rank unless a calculation owns it") |
| **FuelCalculator dormant**, no fuel-yield Provider; fuel KPI catalog **frozen** by prior instruction | D1 fuel yield | dormant owner + frozen catalog |
| **OPS_051 (Production-Target %) RESERVED** — needs objective-target business rule | D1 production-target | reserved KPI, rule not frozen |
| No per-driver **descriptive** Provider (formula exists in ProductivityCalculator/DriverKpiService) | D1 discipline, D2 | provider gap |
| **HSE_001 reserved** — no descriptive HSE Provider | D3 | reserved KPI |
| Role-dashboard operational lists have no single descriptive owner | D2/D4 | scope: operational, not descriptive |

None of these may be filled this phase (creating them = new Providers/Read Models/KPIs, forbidden).

---

## 10. Final Recommendation

**First: D5 — Transport Reports.** Fully READY (OperationsBusinessCommandCenter + TransportTrackingReadModel
own 100% of its metrics), lowest risk, and it establishes the reference migration pattern while incidentally
correcting architecture-violation [A3] through correct wiring (not cleanup).

**Second: D7 — Analytics·Rotations.** Same command center, read model, and fiscal-month owner as D5 →
maximally de-risked by D5; reuses D5's components.

**Third: D6 — Analytics·Fleeti.** READY via FleetiConsumptionReadModel; same page pattern, independent data
source.

**Then (partial): D1 — Admin Home**, ready headline set only, with blocked widgets explicitly deferred.

**Excluded this phase:** D2 Driver, D3 HSE, D4 LogRes, D8 Maintenance board — blocked on owners that do not
exist, or are operational workspaces whose migration would require forbidden refactoring.

---

*End of inventory. Planning only — no code modified, no dashboard migrated. Awaiting approval to begin the
first migration (recommended: D5 Transport Reports).*
