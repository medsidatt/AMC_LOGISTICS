# AMC Fleet Platform — Enterprise Architecture Audit

> **Type:** Verification-only certification audit (no code modified). **Source of truth:**
> `docs/repository-knowledge-graph.md` (frozen). **Method:** 4 parallel evidence-gathering agents
> (security · business-logic/SOLID · database/performance/dead-code · testing/docs/frontend), each
> tracing ownership through the knowledge graph before auditing any file, plus first-hand session
> evidence (fuel decision-ownership, data certification, suite state). Two agent conflicts were
> resolved by direct re-check (recorded in §11, §15). Every finding carries owner · files · dependency
> path · impact · severity · confidence. Generated 2026-07-02.

---

## 1. Executive Summary

The repository is a **mature, disciplined Laravel 12 + Inertia/React 19 monolith** with an explicitly
layered "Operational Intelligence" domain (L0 Read Models → L6 Command Centers) that is **fully
implemented, tested (12/12 calculators, 7/7 read models, all command centers), and documented**. The
frozen architecture matches the code. The fuel subsystem — the most recent large workstream — was
independently certified this session (single decision owner, immutable audit trail, clean historical
data load).

**The audit found no architecture-breaking defect.** It surfaced **one CRITICAL security gap** (the
API authentication path does not enforce suspension / forced-password-change), a **HIGH performance
N+1** on the legacy admin dashboard, **client-side invented thresholds** (vibe coding) in one insights
utility, **hardcoded severity bands** in the theft detectors, and **documented migration residue**
(legacy KPI trio still inlines formulas that calculators own — an in-progress R1.3 state). Documentation
is largely current; two files are obsolete and one is stale.

**Merge/readiness verdict:** the repository is **READY for the dashboard migration** (the BI foundation
is proven and green). It is **NOT yet clean for a production security sign-off** until the API-auth gap
is addressed. All findings are additive to fix; none require re-architecture.

**One pre-existing test failure** (`OperationalParameterServiceTest`, 680≠730 fuel-price seed drift)
persists — unrelated to any subsystem audited, flagged in every prior gate.

---

## 2. Repository Health Score

| Dimension | Score | Basis |
|---|---:|---|
| Architecture & layering | **9.0 / 10** | Frozen L0–L6 implemented exactly; clean ownership; minor SRP breach (1 controller) + R1.3 migration residue |
| Domain design | **9.0 / 10** | Policy/calculator/read-model separation verified; fuel decision-ownership airtight |
| Security | **6.5 / 10** | Web path strong; **API-auth bypass (CRITICAL)** + unfiltered API closures + `$guarded=[]` pattern |
| Performance | **8.0 / 10** | Indexes/eager-loading correct; **one HIGH N+1** on legacy dashboard |
| Frontend | **9.0 / 10** | 1:1 page↔controller, clear state/API ownership, strict TS; 1 vibe-coding utility |
| Database | **9.5 / 10** | FKs/indexes/nullable all correct; no dead tables; 1 dormant column |
| Testing | **8.5 / 10** | 100% domain coverage; **3 zero-test active services**; 1 pre-existing failure |
| Documentation | **8.0 / 10** | Mostly current & exemplary; 2 obsolete + 1 stale doc |
| **Overall** | **8.3 / 10** | Mature, certify-able after the CRITICAL + HIGH items are scheduled |

Scores are descriptive, evidence-weighted; they are not thresholds against a target.

---

## 3. Architecture Health

**Bounded contexts:** Operations (operational-intelligence), Analytics (descriptive BI), Fuel
(import/classification), plus transactional CRUD contexts (transport, fleet, HSE, admin). Boundaries
are respected — Analytics reuses Operations read models/calculators rather than re-querying.

**Dependency direction (verified):** Frontend → Controllers → {Services | Command Centers | Providers}
→ Domain → Models. Read Models are the sole domain DB readers; Calculators are pure; Command Centers
compose only. **DIP:** domain layer inverts onto interfaces (calculators/read-models/registries all
bound in `AppServiceProvider`); the services layer is concrete-by-convention (acceptable Laravel facade
pattern). No type-switch/LSP violations found.

**Cohesion/coupling:** high cohesion per calculator (one rule each). Coupling hotspot: `FleetiSyncService`
composes 10 collaborators (acceptable for a top-level orchestrator) and `TransportTrackingController`
mixes CRUD + reporting + document + theft hooks + AI (SRP breach — §13).

**Documented migration-in-progress (not a defect):** legacy KPI trio (`TruckKpiService`,
`FleetKpiService`, `DriverKpiService`) + `DashboardDataService` still hold inline formulas that the L2
calculators own; ROADMAP R1.3 records this as an incremental migration.

---

## 4. Security Health

| Area | Verdict | Confidence |
|---|---|---|
| SQL injection | Clean — all `whereRaw/selectRaw` parameterized or constant | HIGH |
| XSS | Clean — no `dangerouslySetInnerHTML`; `{!! !!}` only in chart/swagger blades, no user input | HIGH |
| CSRF | Protected — default web group; no exemptions; API is token-based; webhook is HMAC-verified | HIGH |
| Secrets | Clean — all via `config()/env()`; `hash_equals()` timing-safe for cron token + WhatsApp signature | HIGH |
| File uploads | Clean — mimes+size validated; Laravel-generated names; ownership-scoped download | HIGH |
| Web authorization | Strong — all mutating web routes gated by controller-constructor `permission:`/`role:` middleware | HIGH |
| **API authentication** | **CRITICAL GAP** — see §14 | HIGH |
| Mass assignment | Contained — 42 models `$guarded=[]` but controllers validate+whitelist (no `$request->all()`) | HIGH |
| Rate limiting | Partial — login/invite throttled; password-update/upload/API mutations unthrottled | HIGH |
| Logging | Clean — audit trail via `TracksActions`; no password/token logging observed | MEDIUM |

---

## 5. Performance Health

- **Indexes:** every heavy path from knowledge-graph §6 has a supporting composite index
  (`transport_trackings(client_date,truck_id)`, `truck_telemetry_snapshots(truck_id,recorded_at)`,
  `fuel_card_transactions(kpi_eligible,occurred_at)`, `fleeti_daily_records` UNIQUE(truck_id,record_date),
  `truck_stops(truck_id,started_at)`, `trip_segments(truck_id,ended_at)`). **No missing indexes.**
- **Eager loading:** list controllers (transport, trucks, maintenance, fuel, review) all use `with(...)`
  matching rendered relations. **No N+1 in controllers.**
- **N+1 (HIGH):** `FleetKpiService::topDrivers()` runs 3 per-driver queries inside a loop (§15).
- **Scheduled jobs:** all scoped/chunked/windowed; no full-table scans.
- **Scale note (informational):** `FuelImportReferenceReader` loads all refs into memory — negligible at
  current scale (~300 tx), monitor beyond ~5k trucks.

---

## 6. Domain Health

- **Policies:** `ClassificationPolicy` is the **sole** owner of fuel persistence/KPI/review decisions —
  re-verified this session by grep: every `kpi_eligible/proposed_kpi_eligible/review_status/needs_review`
  write originates from `FuelImportService` or `FuelReviewService` via the policy. **No bypass.**
- **Value objects/immutability:** PolicyOutcome, ValidationFindings, all ReadModel DTOs, BusinessEvents
  are readonly; proposal snapshot immutable; review events append-only; `occurred_at` stabilized to
  non-mutating DATETIME (this session's fix).
- **Calculators:** each owns one rule (§12 ownership matrix). Duplication exists only as R1.3 residue in
  the legacy KPI trio.
- **Read Models:** query-only, no writes/thresholds — verified for all 7.
- **Aggregates/repositories:** Read Models act as query repositories; there is no classic aggregate-root
  pattern (Eloquent models are anemic + services) — consistent across the codebase, not a defect.

---

## 7. Frontend Health

- **Page ownership:** 89 pages, each rendered by exactly one controller. No dual-render.
- **State ownership:** no global Redux/Pinia — Inertia shared props (auth/locale/flash) + local state +
  URL query params (drawers) + localStorage (filters). Consistent.
- **API ownership:** 40+ `apiFetch` endpoints, each owned by its controller prefix; RESTful/service-verb.
- **Permissions:** `usePermission()` for visibility only; server re-enforces every mutation (defense in
  depth). Maintenance UI references a dormant permission (`maintenance-assign`) — a deferred feature gate.
- **Routing:** no dead-end links. **TypeScript strict** enabled; `any` density negligible (~5/89 pages).
- **Vibe coding (CRITICAL):** `resources/js/utils/insights.ts` computes business insight thresholds
  client-side (10% trips, 5% tonnage, 2% weight-gap, 10% anomaly, 80% utilization) with no server source
  (§16).

---

## 8. Database Health

- **Tables:** 64; **no dead tables** (verified: `edk_fuel_transactions`/`edk_fuel_recharges` do not exist
  — the rename chain resolved to `fuel_card_transactions`; the earlier "dead table" claim was a
  migration-file artifact, not a live table). No HR/Payroll/Blog remnants.
- **Columns:** all core columns live; one **documented dormant** column `fuel_card_transactions.review_note`
  (notes live on review events).
- **FKs:** correct across core tables; `fuel_card_transactions.truck_id`/`reviewed_by` = `ON DELETE SET NULL`
  (preserves financial truth); tracking/segment/stop = CASCADE; review events = CASCADE. Polymorphic
  `objective_history_entries` intentionally FK-free.
- **Nullable:** decision columns (`kpi_eligible`, `review_status`, `proposed_kpi_eligible`, `needs_review`)
  nullable, no default — DB never decides business outcomes (per prior audit; re-verified).
- **Migrations:** 169, chain coherent with idempotent guards + reversible `down()`. No dead/duplicate.

---

## 9. Testing Health

- **Domain coverage:** 12/12 calculators, 7/7 read models, 4 derivers + source, both KPI registries,
  OperationalIntelligence, 2 operational + 3 BI command centers, 3 BI report translators, 4 BI metrics +
  trend calculators, fuel pipeline 14/14 — all with dedicated tests.
- **Convention:** `DatabaseTransactions` everywhere (dev-DB safe); no `RefreshDatabase`. 3 skipped tests
  are legitimate seed-data conditionals. No obsolete tests, no duplicates.
- **Gaps (HIGH):** 3 ACTIVE services with **zero tests** — `FleetiSyncService`, `TripSegmentBuilderService`,
  `TicketReconciliationService` (all orchestration/scheduler-level). 5 dormant translators untested
  (expected).
- **Suite state:** 394 passed / **1 pre-existing unrelated failure** (`OperationalParameterServiceTest`).
- **BI migration foundation:** fully tested and green.

---

## 10. Documentation Health

| Doc | Verdict |
|---|---|
| operational-intelligence-architecture.md (FROZEN) | **CURRENT** — matches L0–L6 implementation exactly |
| kpi-catalog.md / kpi-registry.md ↔ KpiRegistry.php | **CURRENT & ALIGNED** — 21 IDs across catalog/registry/enum |
| read-model-inventory.md | **CURRENT (conservative)** — should note all 7 RM now exist (incl. Fuel/FleetiConsumption) |
| workspace-standard.md | **CURRENT** — fully adopted across workspaces |
| ROADMAP.md | **CURRENT & authoritative** — updated 2026-07-01; tracks phases accurately |
| fuel-*.md (5 docs) | **CURRENT & exemplary** — dated 2026-07-01; model for domain docs |
| scoring-formulas.md | **MOSTLY CURRENT** — note parameterization (R1.1) upgrade |
| live-fleet-tracking.md | **STALE** — describes GPS presentation removed in Phase 1A; data layer still active |
| microservices-upgrade-plan.md · migration-strategy.md | **OBSOLETE** — abandoned multitenancy/extraction strategy |
| repository-knowledge-graph.md | **CURRENT** — one correction needed: FilterBar/useFilters are ACTIVE, not dormant (§10 register) |

---

## 11. Dead Code Report

**Confirmed DORMANT (built, zero runtime consumers — deferred features):**
- Domain: `FuelCalculator` (bound, no consumer — fuel KPI catalog frozen); 5 translators
  (Dispatch/Fleet/Finance/Maintenance/HSE — no wired command center); 9 RESERVED `BusinessKpiId`s.
- Permissions (seeded, never `can()`-checked): `maintenance-assign`, `maintenance-delete`,
  `rotation-validate`, `user-show`, `user-change-password`, `role-show`, `invitation-show`,
  `inspection-list/create/edit/delete`.
- Schema: `fuel_card_transactions.review_note`; `fuel_import_rejections.needs_review` (persisted, unsurfaced).
- Laravel scaffolding: ForgotPassword/ResetPassword/Verification/ConfirmPassword controllers (register+reset disabled).

**Orphan pages (route exists, no sidebar link — reachable by URL only, auth+role gated):** Projects, Products, Entities.

**ACTIVE-WRITER / UI-ORPHANED (decommission planned — ROADMAP GPS Phase 1B):** theft detector suite +
`theft_incidents` writers (6 detectors → TheftIncidentService; readers only in manual commands/tests).

**CORRECTION to knowledge-graph §10:** `FilterBar` + `useFilters` are **ACTIVE** (used by FuelFilters,
TransportFilters, TransportDashboard) — re-verified by grep. Not dead.

**No dead:** tables, migrations, controllers (all routed), or model classes.

---

## 12. Ownership Matrix (one owner per responsibility — verified)

| Responsibility | Single Owner | Duplication status |
|---|---|---|
| Fuel persistence/KPI/review **decision** | `ClassificationPolicy` | None (verified) |
| Fuel business-fact classification | `FuelImportClassifier` | None |
| Weight-gap rule | `WeightCalculator` | Inlined in TrackingDashboardController + FleetKpiService (R1.3 residue) |
| Capacity default/override | `CapacityCalculator` / `FleetCapacityService` | None (all delegate) |
| Load rate | `UtilizationCalculator` | Inlined ×2 in FleetKpiService (R1.3 residue) |
| Discipline score (40/20/20/20) | `ProductivityCalculator` | Inlined in FleetKpiService:288 (R1.3 residue) |
| Cycle days | `CycleCalculator` | Inlined in Driver/TruckKpiService (R1.3 residue) |
| Fiscal-month (22nd) grouping | `TransportTrackingReadModel` | Duplicated ×2 in TrackingDashboardController |
| Maintenance overdue/bands | `MaintenanceCalculator` | None |
| Inspection SLA validity | `InspectionCalculator` | None |
| Dispatch not-started/rates | `DispatchCalculator` | None |
| Billing readiness | `BillingCalculator` | None |
| Objective ratios | `ObjectiveCalculator` | None |
| Detector base thresholds | `OperationalParameterService` | Severity bands hardcoded in detectors (§16) |
| Persistence (per table) | see knowledge-graph §8 write-owner table | Single-owner per table (verified) |
| Query (domain) | Read Models (7) | Sole DB readers |
| Translation (view) | Executive/Operations translators + 3 BI report translators | None |
| Orchestration (import) | FuelImportService / FleetiImportService | None |
| Authentication | LoginController · MicrosoftAuthController · Api\AuthController + web middleware | **Inconsistent** — API path omits suspension/password checks (§14) |
| Authorization | Spatie RBAC (route + constructor middleware) | Consistent on web; API endpoints unfiltered (§14) |

---

## 13. Architecture Violations

| # | Violation | Owner/Files | Dependency path | Impact | Severity | Confidence |
|---|---|---|---|---|---|---|
| A1 | SRP breach | `TransportTrackingController` (1,135 lines, 9 actions + 16 helpers) | routes/web/transport_basalt_route → controller | Maintainability; mixes CRUD/report/upload/theft/AI | HIGH | HIGH |
| A2 | R1.3 migration residue — calculators' formulas inlined | `FleetKpiService:288` (discipline), `:98-107,143,249` (load-rate); `DriverKpiService:131-157` (cycle) | HomeController/Truck/DriverController → KPI trio | Divergent dashboard numbers risk if formulas edited in one place | HIGH | HIGH |
| A3 | Fiscal-month logic duplicated | `TrackingDashboardController:30-42,114-125,426-431` | routes → controller (bypasses TransportTrackingReadModel owner) | Two sources for the 22nd-cycle rule | HIGH | HIGH |
| A4 | Documentation drift | knowledge-graph §10 (FilterBar), read-model-inventory, scoring-formulas | docs only | Misleading to readers | LOW | HIGH |

---

## 14. Security Findings

| # | Finding | Owner/Files | Dependency path | Impact | Severity | Confidence |
|---|---|---|---|---|---|---|
| S1 | **API auth bypasses suspension + forced-password** | `app/Http/Controllers/Api/AuthController.php:11-29` (issues Sanctum token with no `is_suspended`/`must_change_password` check; `EnsureUserIsActive`/`CheckPasswordChange` are web-group only) | `POST /api/v1/login` → token → `auth:sanctum` routes | A suspended user retains full API access via token | **CRITICAL** | HIGH |
| S2 | Unfiltered API list closures | `routes/api.php` (`/transport_tracking`, `/trucks`, `/drivers`, `/providers`, `/transporters` return all rows, no role/permission filter) | `auth:sanctum` group | Broad data exposure to any API token holder | HIGH | HIGH |
| S3 | Missing rate limits | `PUT /auth/password/update`, `PUT /auth/force-password/update`, `POST /transport_tracking/*/documents`, `POST /api/v1/*` mutations | routes | Brute-force / upload-DoS surface | MEDIUM | HIGH |
| S4 | Mass-assignment pattern | 42 models `$guarded=[]` | controllers (currently validate+whitelist) | Latent risk if a future controller passes raw input | MEDIUM | HIGH |
| S5 | No per-record ownership on shared resources (Manager sees all transport/fuel) | TransportTracking/Fuel controllers | permission-gated, no row scoping | Horizontal exposure within a role — **design choice**, recorded | LOW | HIGH |

Authorization on **web** endpoints: fully gated (verified — every mutating route has constructor
permission/role middleware). No web authorization gaps.

---

## 15. Performance Findings

| # | Finding | Owner/Files | Dependency path | Impact | Severity | Confidence |
|---|---|---|---|---|---|---|
| P1 | **N+1** — 3 per-driver queries in a loop | `FleetKpiService::topDrivers()` (~lines 189-240) | `HomeController::index` → FleetKpiService → per-driver TransportTracking/DailyChecklist/DailyChecklistIssue | +45–60 queries on every admin dashboard load (~15-20 drivers) | HIGH | HIGH |
| P2 | In-memory reference preload (scale watch) | `FuelImportReferenceReader::read()` | FuelImportService import path | Negligible now; risk beyond ~5k trucks | LOW | HIGH |

No N+1 in list controllers (all eager-loaded); no duplicated per-request queries; scheduled jobs scoped.

---

## 16. Business Logic Findings

| # | Finding | Owner/Files | Dependency path | Impact | Severity | Confidence |
|---|---|---|---|---|---|---|
| B1 | **Invented client-side thresholds (vibe coding)** | `resources/js/utils/insights.ts:18,29,58,81,93` (10%/5%/80%/2%/10%) | dashboards render generateAdminInsights | Business rules with no server source of truth; can diverge from OperationalParameters | **CRITICAL** (as a rule-ownership violation; not a security issue) | HIGH |
| B2 | Hardcoded severity bands in detectors | `FuelEventDetectorService:95` (≥50L), `UnauthorizedStopDetector:59` (≥60min), `RouteDeviationDetector:65-67` (100/30km), `WeightGapDetector:44-47` (1000/500kg) | detectors → TheftIncidentService | Severity levels not parameterized (base thresholds ARE); theft layer UI-orphaned/decommission-planned | MEDIUM | HIGH |
| B3 | Inline weight-anomaly threshold | `TransportTrackingController:919` `config('logistics.weight_anomaly_threshold',0.2)` | dashboard action | Threshold outside OperationalParameterService | MEDIUM | HIGH |
| B4 | Radius doc drift | `GeoService` search radius (code) vs ROADMAP "250m" | places detection | Doc/impl mismatch; verify intent | LOW | MEDIUM |

Policy bypass: **none**. Read-model purity: **verified**. No TODO/FIXME/placeholder in production paths.
`operations/Exceptions` page ships `{items:[]}` — confirm whether stub (Insufficient evidence on intent).

---

## 17. Technical Debt (documented, not defects)

- R1.3 KPI-trio → calculator migration incomplete (A2) — ROADMAP-tracked.
- GPS Phase 1B: decommission theft writers + `theft_incidents` (ROADMAP technical-debt section).
- Fuel `FuelCalculator` + 9 reserved BI KPIs dormant pending the frozen fuel-KPI catalog decision.
- 2 obsolete docs + 1 stale doc (§10) — archival candidates.
- Dormant permission strings (§11) — catalog vs enforcement gap.
- Zero-test orchestration services (§9).

---

## 18. Dashboard Readiness

**READY.** The BI foundation for the migration is fully in place and tested: `BusinessKpiRegistry`
(10 active KPIs) → Fleet/Operations/Productivity metrics calculators → MovementTrendCalculator → 3 BI
command centers → 3 report translators → ExportController (HTML/CSV/JSON), all fed by the pure Read
Models and covered by `Analytics/Business*Test`. The descriptive fuel data layer (`FuelDashboardDataProvider`
→ `/fuel/analytics`) is built, tested, and asserted free of KPI/threshold vocabulary. Read Models expose
every field the example dashboard cards need (cost dimension complete; consumption dimension added via
`FleetiConsumptionReadModel`). Migration does not require new queries — calculators consume existing read
models. **No test gaps block the migration.**

Caveat carried forward (not a blocker): fuel efficiency metrics are only computable for the May–June
Fleeti overlap window; cost metrics span the full 6 months.

---

## 19. Merge Readiness

| Gate | Status |
|---|---|
| Tests green | ⚠️ 394 pass / 1 pre-existing unrelated failure (OperationalParameterServiceTest 680≠730) |
| Architecture integrity | ✅ frozen layering intact; ownership single-owner (calculators R1.3-noted) |
| Fuel decision ownership | ✅ ClassificationPolicy sole owner (re-verified) |
| Web security | ✅ authz gated end-to-end |
| **API security** | ❌ **CRITICAL S1** must be resolved before a production security sign-off |
| DB integrity | ✅ FKs/indexes/nullable correct |
| Docs | ⚠️ 2 obsolete + 1 stale (non-blocking) |

**Recommendation:** the branch is **mergeable for continued development / dashboard migration**, but a
**production release gate should block on S1 (API auth)** and the repository-wide **pre-existing test
failure** should be reconciled by its owning workstream. This audit modifies nothing — these are
verification verdicts, not applied fixes.

---

## 20. Prioritized Action Plan (recommendations only — no fixes applied)

| Priority | Item | Refs | Owner surface |
|---|---|---|---|
| P0 (CRITICAL) | Enforce `is_suspended` + `must_change_password` in the API auth path; scope the API list closures by role | S1, S2 | Api\AuthController, routes/api.php |
| P0 (CRITICAL) | Move insight thresholds server-side (source them from OperationalParameterService) | B1 | resources/js/utils/insights.ts |
| P1 (HIGH) | Batch `FleetKpiService::topDrivers()` queries (remove per-driver loop) | P1 | FleetKpiService |
| P1 (HIGH) | Add integration tests: FleetiSyncService, TripSegmentBuilderService, TicketReconciliationService | §9 | tests/Feature |
| P1 (HIGH) | Complete R1.3: delegate FleetKpiService/DriverKpiService formulas to calculators; centralize fiscal-month grouping | A2, A3 | KPI trio, TrackingDashboardController |
| P2 (MEDIUM) | Reconcile the pre-existing OperationalParameterServiceTest (680≠730) | §9 | OperationalParameter workstream |
| P2 (MEDIUM) | Parameterize detector severity bands + weight-anomaly threshold | B2, B3 | detectors, OperationalParameterService |
| P2 (MEDIUM) | Add rate limits to password-update / upload / API mutations; migrate `$guarded=[]` → `$fillable` | S3, S4 | routes, models |
| P2 (MEDIUM) | Split TransportTrackingController (CRUD / reports / documents) | A1 | TransportTrackingController |
| P3 (LOW) | Archive microservices/migration docs; refresh live-fleet-tracking, scoring-formulas, read-model-inventory; correct knowledge-graph §10 (FilterBar) | §10, A4 | docs |
| P3 (LOW) | Decide/monitor: dormant permissions, review_note column, orphan pages, GPS Phase 1B theft decommission | §11, §17 | schema, seeder, ROADMAP |

---

*End of audit. Verification-only: no code was modified, no fixes applied. Every finding is traced to an
owner, files, and dependency path per the frozen knowledge graph. Two inter-agent conflicts (FilterBar
liveness; "dead" fuel tables) were resolved by direct re-check and corrected above.*
