# Phase 1 — Dashboard Migration — Completion Report (Hardened)

> Formal closure document for **Phase 1 — Dashboard Migration** (roadmap Forward Phases).
> Verification-grounded: every certification below was re-checked against the working tree on
> 2026-07-02 (grep sweeps + `tsc`). No application code was modified by this closure pass.

---

## 1. Scope Executed

**Migrated (9 user-facing dashboards, approved order):**
1. Admin Home `/dashboard` (`Dashboard.tsx`) — provider-backed headline KPIs (`HomeDashboardDataProvider` → BI metric calculators) + executive reduction
2. Driver Home (`DriverDashboard.tsx`)
3. HSE Home (`HseDashboard.tsx`)
4. Logistics Responsible Home (`LogisticsResponsibleDashboard.tsx`)
5. Transport Dashboard `/transport_tracking/dashboard` (`TransportDashboard.tsx`)
6. Transport Analytics `/dashboard/trackings` (`transport-trackings/Reports.tsx`)
7. Fleet Analytics `/dashboard/rotations` (`analytics/Rotations.tsx`)
8. Maintenance `/maintenance` (`maintenance/Index.tsx` + `MaintenanceDetailsDrawer.tsx`)
9. Fuel Analytics `/dashboard/fleeti` (`analytics/Fleeti.tsx`)

**Already compliant (excluded — no work needed):** Executive Command Center `/dashboard/executive` ·
Operations Command Center `/dashboard/operations` · BI Executive/Operations/Fleet `/business/*` ·
Fuel descriptive endpoint `/fuel/analytics`.

**Not found:** Theft Dashboard (presentation removed in GPS Phase 1A; writers scheduled for Phase 1B/Phase 2).

**Related pass (same enterprise rules, app-wide):** the Global UI Rule "no business-logic explanations in
the UI" removed 12 explanatory/formula strings across 8 files (fuel drawers, truck sections, planning,
places, reports) prior to per-dashboard migration.

---

## 2. Global UI Rules Compliance

Certification method: pattern sweeps across all 9 migrated surfaces + `tsc --noEmit` (2026-07-02).

| Rule | Verdict | Evidence |
|---|---|---|
| No React business calculations / aggregations | ✅ PASS | grep for `.reduce(`/business arithmetic: only chart-height scaling (visualization, allowed) and one sort comparator remain |
| No React-derived percentages | ⚠️ **1 EXCEPTION** (below) | `calcChange` in `Dashboard.tsx` |
| No React-derived comparisons / cross-period comparisons | ⚠️ **1 EXCEPTION** (below) | "vs hier / vs mois dernier" in `Dashboard.tsx` |
| No React business thresholds | ✅ PASS (display values) | FuelBar (`<30/<80/<150`, `/400`), insight thresholds, conflict filters all removed. Styling-only color bands remain (registered §3, values unaffected) |
| No duplicated KPIs / widgets | ✅ PASS | 7 documented duplication removals (HSE/LogRes count strips, Driver "À faire" trio, Fuel critical banner + Carburant column, T-Analytics Total row, Transport "Normales") |
| No explanatory methodology / business formulas / implementation notes | ✅ PASS | grep `formule|méthodolog|calculé à partir|basé sur|source:` → clean |
| No technical metadata (Created/Updated/Imported by, Synced, last sync, GPS status, source system, internal IDs) | ✅ PASS | grep clean; 9 metadata elements removed (GPS badges/columns, last-sync, inspector columns, Fleeti-km, sync texts) |
| No fiscal-month labels (22→21) | ✅ PASS | grep `22→21|22/mm|21/mm|fiscal` → clean (rule itself remains backend-owned — Phase 3.1) |
| Empty-state policy | ✅ PASS | "—"/"Aucune activité"/"Aucune donnée" applied on 6 surfaces; exception-count zeros (e.g. 0 Urgent) intentionally kept as meaningful |
| Allowed presentation ops only (format/sort/paginate/filter/visualize) | ✅ PASS | Fleeti list `sort((a,b)=>a.litres-b.litres)` = display sort; chart `value/max` = visualization scaling |

### Declared exception (not hidden)
**EX-1 — `resources/js/pages/Dashboard.tsx:69-70,100,109`** — the two activity KPIs (Rotations
aujourd'hui, Tonnage du mois) still compute a change-% in React (`calcChange(today, yesterday)` /
`(month, lastMonth)`) and render "vs hier / vs mois dernier". Both raw values are backend-provided; the
**percentage derivation and the cross-period comparison are frontend** — a violation of the final rules
that survived the earlier executive cleanup. Per this closure's constraints (no code changes, no
dashboard reopening) it is **declared, owned, and registered**: remediation = backend-provided deltas
(owner: `HomeController` payload / legacy `DashboardDataService`) **or** removal of the change badges —
scheduled in the Phase 2 register (F-7).

---

## 3. Deferred Phase 2 Register (consolidated — single source; per-dashboard reports no longer authoritative for deferrals)

### Frontend Cleanup
- **F-1 Dead Props/DTO fields** left after widget removals: `DriverDashboard` (movement_status, last_sync, recentTrips, checklistHistory), `HseDashboard`/`LogisticsResponsibleDashboard` (inspector, vehicle_photo_url, recentInspections), `TransportDashboard` (months, monthlyWeights, timelineEvents, unused kpi pct fields, suspiciousDrivers), `analytics/Fleeti` (connected, synced_recently, last_sync, fleeti_connected, fleeti_km, fuel_litres, last_synced on fleetTable), `Dashboard` (unused legacy kpi fields after executive cut).
- **F-2 `utils/insights.ts`** — now unreferenced by any dashboard (client-side invented thresholds, audit B1): delete.
- **F-3 Styling-only hardcoded thresholds** — `RatioCard.ratioColor` (0.8/0.5), gap color bands in `Reports.tsx`/`Rotations.tsx` (±5, −500), FuelBar removed already. Decide: parameterize server-side or drop coloring.
- **F-4 `alert()` error UX** in `FuelImportDrawer` (4 sites) → toast pattern.
- **F-5 Truck-page sections** (`FuelComparisonSection`, `TruckKpiSection`) — dashboards-adjacent but Truck-workspace-owned; audit under workspace scope.
- **F-6 Unused components/hooks check** post-removals (e.g., `TopList`, `InsightCard`, `TonnageChart`, `PeriodFilter`, `useExport`/`useFilters` consumers) — re-grep before deleting (knowledge-graph §10 correction showed FilterBar/useFilters ACTIVE).
- **F-7 EX-1 remediation** — Admin Home change badges: backend deltas or removal.

### Backend Cleanup
- **B-1 Legacy KPI trio** (`TruckKpiService`, `FleetKpiService`, `DriverKpiService`) + `DashboardDataService` — R1.3 residue: discipline 40/20/20/20 inline (`FleetKpiService:288`), load-rate ×2, cycle-days inline; **N+1** `FleetKpiService::topDrivers` (audit P1).
- **B-2 Fiscal-month inline duplicates** (`TrackingDashboardController`, `DashboardDataService` — audit A3) — consolidate to the read-model owner *(rule change itself = Phase 3.1, not Phase 2)*.
- **B-3 Oversized controller** — `TransportTrackingController` SRP split (audit A1).
- **B-4 Backend-ownership blockers from Phase 1** (only if the business wants the widget back): transport conflict count; per-product Écart (`TrackingDashboardController@rotations`); maintenance items total (record payload); maintenance form-prefill default (+ hardcoded 9000 km fallback); per-truck fuel-level classification + tank capacity (`@fleeti`; classification gated on Phase 4 fuel KPI catalog); monthly totals row (Reports payload).
- **B-5 Theft-layer decommission** (GPS Phase 1B): 6 detectors + `TheftIncidentService` + `theft_incidents` (+ splits), per ROADMAP.
- **B-6 Dead endpoints/pages review**: orphaned `TransportDashboard` route consolidation (ROADMAP), legacy Excel import route, orphan pages (Projects/Products/Entities sidebar-less).
- **B-7 `review_note` dead column**; `fuel_import_rejections.needs_review` unsurfaced; dormant translators ×5; `FuelCalculator` dormant (Phase 4 gate).

### Architecture Cleanup
- **A-1 Detector severity bands hardcoded** (50L/60min/100-30km/1000-500kg — audit B2) + `weight_anomaly_threshold` config inline (B3) → OperationalParameterService.
- **A-2 `$guarded = []`** on 42 models → explicit `$fillable` (audit S4).
- **A-3 Zero-test ACTIVE services**: `FleetiSyncService`, `TripSegmentBuilderService`, `TicketReconciliationService` (+ pre-existing `OperationalParameterServiceTest` 680≠730 seed reconciliation).
- **A-4 Docs**: archive `microservices-upgrade-plan.md` + `migration-strategy.md`; refresh `live-fleet-tracking.md`, `scoring-formulas.md`, `read-model-inventory.md`; knowledge-graph §10 FilterBar correction.
- **A-5 Dormant permissions** (maintenance-assign/delete, rotation-validate, user-show/change-password, role-show, invitation-show, inspection-*) — enforce or drop.

### Security (audit S-findings — Phase 2 hardening)
- **S-1 CRITICAL** API auth bypass: `Api\AuthController` issues Sanctum tokens without `is_suspended`/`must_change_password`; web-only middleware doesn't cover API.
- **S-2 HIGH** unfiltered API list closures (`routes/api.php` — trucks/drivers/transports/providers/transporters).
- **S-3 MEDIUM** rate limits missing (password update, force-password, uploads, API mutations).
- **S-4** `AJAX_TOKEN` hardcoded fallback in `config/app.php` (ROADMAP).

### Business Rule Migration (reference only — Phase 3, NOT Phase 2 work)
- **3.1 Calendar Month Migration** (fiscal 22→21 → calendar month) — scheduled; see roadmap Phase 3. UI labels already removed; the backend rule (`fiscal_month_start_day`, read-model grouping) intentionally untouched.

---

## 4. Dashboard Migration Metrics (repository-verifiable)

| Metric | Value | Basis |
|---|---|---|
| Dashboards migrated | **9** | §1 list, diffs present |
| Dashboards already compliant (excluded) | **6** | knowledge-graph M1–M6 |
| Dashboards not found | **1** (Theft) | GPS Phase 1A record |
| Frontend files modified (9 migrations) | **10** | 9 pages + `MaintenanceDetailsDrawer.tsx` |
| Frontend files modified (related Global-UI-rules pass) | **8** | that pass's report |
| Backend files touched in Phase 1 | **1 modified + 2 created** | migration #1 only (approved additive): `HomeController.php` wiring; new `HomeDashboardDataProvider.php` + its test. Migrations #2–#9: **0 backend** |
| New tests added (Phase 1) | **3** (provider) | `HomeDashboardDataProviderTest` (62 assertions) |
| Documented REMOVE decisions (widgets + rendered elements) | **46** | audit tables: 11+5+3+2+6+3+3+2+11 |
| Duplicated widgets removed | **7** | itemized in §2 |
| React business-calculation sites removed | **9** | load-rate denominator; Transport `pctReceived−100`; Transport conflict filter-count; T-Analytics totals `.reduce`×4 (one row); F-Analytics `client−prov`; Maintenance items `.reduce`; FuelBar pct+threshold classification; Fuel GPS filter counts; insight-generator invocations (Admin+Transport) |
| Technical-metadata elements removed (dashboards) | **9** | Driver last-sync + GPS badge; HSE + LogRes inspector columns; Fuel GPS sublabel/column, last-sync column, Fleeti-km column, sync empty-state |
| Methodology/explanatory texts removed (dashboards) | **8** | Reports ×2, Rotations ×3, Maintenance ×1, Fuel ×2 |
| Methodology/explanatory texts removed (Global-UI pass) | **12** | that pass's itemized table |
| Widgets **moved** (re-implemented on owner pages) | **0** | removals carried owner labels; no re-implementation performed (by design — backend frozen) |
| Empty-state policy applied | **6 surfaces** | Admin, Driver, HSE, LogRes, Transport, Fuel Analytics |
| Per-widget audit tables produced | **9/9** | one per migration report |
| Lines-of-code delta per widget | **Insufficient evidence** | not tracked per widget; only per-file diffs exist |
| User-visible load-time impact | **Insufficient evidence** | not measured |

---

## 5. Phase Exit Criteria — Phase 1 Status: **COMPLETE**

| Criterion | Status |
|---|---|
| Every dashboard audited (widget-level KEEP/REMOVE/MOVE) | ✅ 9/9 tables produced |
| Every dashboard migrated | ✅ 9 migrated · 6 already compliant · 1 non-existent |
| Enterprise UI rules enforced | ✅ certified §2 — with **one declared exception (EX-1)**, owned & registered (F-7) |
| Presentation ownership respected | ✅ every kept widget has one owner; every removal names its owner |
| Backend unchanged | ✅ frozen since migration #2; sole Phase 1 backend touch = approved additive provider wiring (#1) |
| TypeScript clean | ✅ `tsc --noEmit` = 0 errors (re-run at closure) |
| No Phase 1 blockers remaining | ✅ all blockers are Phase 2/3/4 items, consolidated in §3 |
| Ready for Phase 2 | ✅ register (§3) is the Phase 2 entry backlog |

**Known repository-wide note (not a Phase 1 criterion):** the full test suite carries **one pre-existing
unrelated failure** (`OperationalParameterServiceTest`, 680≠730 seed drift — flagged in every audit since
before Phase 1; owner: OperationalParameter workstream; registered A-3).

**Phase 1 is COMPLETE and FROZEN.** Dashboards may not be reopened except through Phase 2 register items.
Phase 2 (Project Cleanup & Enterprise Hardening) has **NOT** started.
