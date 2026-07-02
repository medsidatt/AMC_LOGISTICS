# AMC Fleet Platform — Repository Knowledge Graph

> **Purpose:** Complete factual map of the repository — modules, dependencies, flows, ownership, and
> liveness — produced as the foundation for the Enterprise Architecture Audit (pre-dashboard-migration).
> **Method:** bottom-up C4-style analysis (Code → Component → Container → Context), four parallel
> deep-explorations (HTTP/auth · Domain · Services/Models/Console · React frontend) cross-referenced
> against routes, DI bindings, and the scheduler. **No findings, no critique — documentation only.**
> Generated 2026-07-02.

---

## 0. System Context (C4 Level 1)

**AMC Fleet Platform** — enterprise transportation operations platform (Senegal→Mauritania basalt
corridor). Laravel 12 + Inertia + React 19 monolith, MySQL (64 tables), served per-role
(Super Admin / Admin / Manager / Driver / HSE Agent / Logistics Responsible).

**External systems:**

| System | Direction | Integration point |
|---|---|---|
| Fleeti GPS API | inbound (poll) | `FleetiService` (Guzzle) via 2 scheduled sync commands |
| EDK fuel-card exports (CSV files) | inbound (upload/CLI) | `EdkImportParser` → fuel import pipeline |
| Microsoft Azure AD | inbound (OAuth) | `MicrosoftAuthController` + `SilentSso` (Socialite) |
| Microsoft SharePoint (Graph API) | outbound | `SharePointStorageService` + checklist services |
| WhatsApp (Meta Cloud API) | outbound + webhook | `WhatsappClient` / `WhatsappWebhookController` |
| OpenAI | outbound | `TransportTrackingController@analyze` (ask-ai) |
| Pusher/Echo | outbound | `resources/js/echo.ts` (wired; page usage minimal) |
| URL-cron (Infomaniak shared hosting) | inbound | `GET /cron/run[/{job}]?token=` → `CronController` |

**Scale:** 458 PHP app files · 184 TS/TSX files · 169 migrations · 71 test files · 89 React pages ·
~35 rendering controllers · 23 architecture docs (see `docs/README.md`).

**Key packages:** spatie/laravel-permission, inertiajs, sanctum, socialite (+azure), maatwebsite/excel,
phpoffice (spreadsheet/word), dompdf/fpdi, microsoft-graph, openai-php, pusher. Frontend: react 19,
apexcharts, leaflet, fullcalendar, tailwind 4, framer-motion, lucide.

---

## 1. Container View (C4 Level 2) — Layered Module Map

```
Browser (React 19 + Inertia SPA)
  └── resources/js: 89 pages · 25 ui components · ~45 domain components · 6 hooks
HTTP boundary
  └── routes/web.php + routes/web/*.php (13 area files) + routes/api.php (Sanctum v1) + cron routes
  └── app/Http/Controllers (~45) — thin; middleware: auth, permission:*, role:*
Application services
  └── app/Services/** — Fleeti sync stack · fuel import/review · KPI services (legacy trio) ·
      theft detectors · trip/stop analysis · planning/optimization · SharePoint · WhatsApp
Domain (Operational Intelligence — FROZEN architecture, docs/operational-intelligence-architecture.md)
  └── app/Domain/Operations: ReadModels(L1) → Parameters → Calculators(L2) → Events+Derivers(L3)
      → KPI Registry(L4) → Intelligence(L5) → Translators(L6) → Command Centers
  └── app/Domain/Analytics: BusinessKpiRegistry → Metrics → Trends → Reports → BI Command Centers → Exports · Fuel (descriptive provider)
  └── app/Domain/Fuel: Parsing DTOs → Classification → ClassificationPolicy (sole decision owner)
Persistence
  └── app/Models (64 tables) — write-ownership table in §8
Background
  └── Scheduler (13 entries) · database queue (2 jobs) · console commands (~20)
```

**Module dependency rule (as implemented):** Frontend → Controllers → {Services | Command Centers |
Providers} → Domain → Models. Read Models are the only domain DB readers; Calculators are pure;
Command Centers compose only. Legacy KPI services (`TruckKpiService`/`FleetKpiService`/`DriverKpiService`)
still query models directly and consume Calculators via shims (R1.3 migration state).

---

## 2. Route Ownership (full table = agent report §1; summary)

| Area | Prefix | Route file | Controller(s) | Gate |
|---|---|---|---|---|
| Dashboards | `/dashboard[/executive|/operations]` | web.php | Home / ExecutiveDashboard / OperationsDashboard | auth (+role routing in Home) |
| BI dashboards + exports | `/business/*` | web.php | BusinessDashboard, Export | auth |
| Fuel workspace/import/analytics | `/fuel/*` | web.php | FuelImport | `permission:fuel-import` |
| Fuel review | `/fuel/review/*` | web.php | FuelReview | `permission:fuel-import` |
| Operations cycle | `/planning /dispatch /realisation /assignments /reconciliation /exceptions` | operations_route.php | Operations, DailyDispatch | `fleet-roster-plan`, `daily-dispatch-list`, `driver-truck-assign`, `live-fleet-view`, `fleet-settings-edit` |
| Transport & fleet | `/transport_tracking /trucks /drivers /providers /transporters /maintenance` | transport_basalt_route.php | TransportTracking, Truck, Driver, Provider, Transporter, Maintenance | per-CRUD permissions (constructor middleware) |
| HSE / inspections | `/hse/inspections /logistics/inspections /logistics/validation` | hse_route.php | Hse, LogisticsInspection, LogisticsManager | auth (+workflow roles) |
| Logistics planning suite | `/logistics/{optimization,demands,rest-windows,objectives,affectations,availability,objective-history}` | optimization_route.php | FleetOptimization, ClientDemand, TruckRestWindow, FleetObjective, TruckDriverAssignment, Availability, ObjectiveHistory | `fleet-optimization-view/run` + auth |
| Places (geofences) | `/logistics/places` | theft_detection_route.php | Place | read `logistics-dashboard`; write `role:Admin\|Super Admin` |
| Admin | `/users /roles /auth/invitations /admin/audit-logs /settings/fleet /entities /projects /products` | user/role/entity/project/product routes | User, Role, Invitation, AuditLog, FleetSettings, Entity, Project, Product | user-*/role-*/invitation-* etc. |
| Auth | `/login /auth/microsoft[...] /auth/force-password` | web.php | Login, MicrosoftAuth, User | guest/auth; register+reset disabled |
| API v1 | `/api/v1/*` | api.php | Api\Auth, KilometerTracking, FleetiSync, Truck | `auth:sanctum`; login `throttle:6,1` |
| Webhooks | `/api/webhooks/whatsapp` | api.php | WhatsappWebhook | HMAC signature middleware |
| Cron | `/cron/run[/{job}]` | cron_route.php | Cron | `CRON_TOKEN` |

Legacy redirect shims: `/logistics/planning→/dispatch`, `/logistics/fleet-roster→/planning`,
`/logistics/objectives→/planning/objectives`, `/logistics/availability→/planning/availability`,
`/logistics/affectations→/assignments`, `/settings/operations-calendar→/planning/calendar`,
`/fuel/import→/fuel`, `/operations→/planning`.

---

## 3. Authentication & Authorization Flow

**Authentication (3 entry paths):**
1. **Form login** — `POST /login` (throttle 5/min) → `LoginController` (AuthenticatesUsers). `authenticated()` hook: blocks `is_suspended`, routes Drivers to `/dashboard`.
2. **Microsoft SSO** — guests are intercepted (`redirectGuestsTo` → `SilentSso::nextRedirectFor`) and tried silently (`prompt=none`, cooldown on failure) → `MicrosoftAuthController@callback` resolves email → existing user login, or invitation completion.
3. **API** — `POST /api/v1/login` (throttle 6/min) → Sanctum bearer token.

**Registration is invitation-only:** `InvitationController@sendInvitation` (permission `invitation-create`) creates the User immediately (random password, `must_change_password=true`), records `Invitation` (token, 7-day expiry), sends `InvitationMail`. The public accept link (`throttle:10,1`) now redirects to `/login`; Microsoft-OAuth acceptance path validates token+email in session and completes login.

**Session guards (global web stack, bootstrap/app.php):** `HandleInertiaRequests` (shares user+roles+permissions+flash+locale) · `SetLocale` · `EnsureUserIsActive` (suspension logout + SSO cooldown) · `CheckPasswordChange` (forces `/auth/force-password`).

**Authorization = Spatie RBAC, 4 evaluation points:** route middleware (`permission:`/`role:`) → controller constructor middleware (per-CRUD) → runtime `can()`/`hasRole()` (e.g. Home role-routing, sidebar) → frontend `usePermission()` (visibility only; server re-checks).

**Roles → permission sets** (seeded in `RoleAndPermissionSeeder`; definitions in `app/Permissions/*.php`):
Super Admin (all) · Admin (all minus role-create/delete) · Manager (fleet/logistics CRUD, no user/role admin) · Driver (none — role-gated self-service pages) · HSE Agent, Logistics Responsible (`live-fleet-view` via migration `2026_05_22_100300`, plus role-routed dashboards/workflows).

**Permission liveness:** all sidebar/route permissions active. Seeded-but-unreferenced in code:
`maintenance-assign`, `maintenance-delete`, `rotation-validate`, `user-show`, `user-change-password`,
`role-show`, `invitation-show`, `inspection-list/create/edit/delete` (inspection pages currently gate by
auth+role flow, the permission strings are seeded but not checked). Status recorded as **DORMANT** (defined, unused).

---

## 4. Domain Graphs (Operational Intelligence — frozen layering)

### 4.1 Read Model graph (L1 — sole domain DB readers; all bound in AppServiceProvider)

| Read model | Feeds | Status |
|---|---|---|
| `TransportTrackingReadModel` | RotationCalculator · TransportTrackingEventDeriver (via WeightCalculator) · OperationsMetricsCalculator · ProductivityMetricsCalculator | ACTIVE |
| `FleetReadModel` | FleetMetricsCalculator · capacity resolution | ACTIVE |
| `MaintenanceReadModel` | MaintenanceEventDeriver | ACTIVE |
| `InspectionReadModel` | InspectionEventDeriver | ACTIVE |
| `DispatchReadModel` | DispatchEventDeriver | ACTIVE |
| `FuelReadModel` | `FuelDashboardDataProvider` (→ `GET /fuel/analytics`) | ACTIVE (descriptive only; BI KPI consumers reserved) |
| `FleetiConsumptionReadModel` | `FuelDashboardDataProvider` | ACTIVE (same) |

DTOs live in `ReadModels/Data/` (19 immutable projections; no behavior).

### 4.2 Calculator graph (L2 — each owns exactly ONE business rule)

| Calculator | Owned rule | Consumers | Status |
|---|---|---|---|
| WeightCalculator | gap + threshold violation (param `weight_*`) | TransportTrackingEventDeriver; legacy Driver/FleetKpiService shims | ACTIVE |
| CapacityCalculator | per-truck capacity ?: global default | ProductivityMetricsCalculator; legacy KPI services | ACTIVE |
| RotationCalculator | fiscal-month tonnage/rotation aggregation | KPI registry (OPS_003/004/008) | ACTIVE |
| CycleCalculator | avg days between consecutive loads | KPI registry | ACTIVE |
| UtilizationCalculator | load rate = tonnage/(capacity×rotations) | ProductivityMetricsCalculator (BI PRD_001) | ACTIVE |
| MaintenanceCalculator | km-overdue + red/yellow/green bands (param ratio) | MaintenanceEventDeriver | ACTIVE |
| InspectionCalculator | validity vs SLA days (param) | InspectionEventDeriver | ACTIVE |
| DispatchCalculator | not-started predicate + start/completion rates | DispatchEventDeriver | ACTIVE |
| ProductivityCalculator | discipline score 40/20/20/20 | KPI registry | ACTIVE |
| ObjectiveCalculator | achievement/coverage/deficit ratios | KPI registry | ACTIVE |
| BillingCalculator | billing readiness + blocked revenue | KPI registry (FIN_100-102) | ACTIVE |
| FuelCalculator | litres/tonne yield | **no runtime consumer** (fuel KPI catalog frozen) | DORMANT (bound, awaiting KPI decisions) |

### 4.3 Event flow (L3→L6)

```
ClockDerivationContextFactory → context(asOf, period)
DerivedBusinessEventSource (dedup by eventId+entity)
  ← MaintenanceEventDeriver  → MaintenanceOverdue
  ← InspectionEventDeriver   → InspectionExpired
  ← TransportTrackingEventDeriver → WeightAnomalyDetected
  ← DispatchEventDeriver     → TruckUnavailable, MissingTransportTicket
  → OperationalIntelligence (event → KPI lookup in KpiRegistry → OperationalConclusion)
      → ExecutiveTranslator  → ExecutiveCommandCenter  → ExecutiveDashboardController  → executive/Index
      → OperationsTranslator → OperationsCommandCenter → OperationsDashboardController → operations/CommandCenter
```
14 BusinessEvent subclasses exist; 5 currently derived. Enums: Severity/Impact/Owner/EventId.

### 4.4 Translator graph

ACTIVE: ExecutiveTranslator, OperationsTranslator (+ ConclusionArranger/SeverityTally/PresentationCard/Queue utilities), and the 3 BI report translators (Executive/Operations/FleetReportTranslator ← AbstractReportTranslator).
DORMANT (built for deferred command centers, no consumer wired): DispatchTranslator, FleetTranslator, FinanceTranslator, MaintenanceTranslator, HSETranslator.

### 4.5 Command Center graph

```
Operational (exception pipeline):
  ExecutiveCommandCenter / OperationsCommandCenter
    ← BusinessEventSource + OperationalIntelligence + {Executive|Operations}Translator
    → {Executive|Operations}DashboardResponse → controllers → Inertia pages

Business (descriptive BI pipeline):
  {Executive|Operations|Fleet}BusinessCommandCenter (← AbstractBusinessCommandCenter)
    ← BusinessKpiRegistry (KPI set per center)
    ← Fleet/Operations/ProductivityMetricsCalculator (current + previous period)
    ← MovementTrendCalculator (Δ, Δ%, direction)
    ← {Executive|Operations|Fleet}ReportTranslator
    → BusinessDashboardResponse → BusinessDashboardController (pages) + ExportController (HTML/CSV/JSON via ExportEngineResolver)

Descriptive fuel (registry-independent, KPI catalog frozen):
  FuelDashboardDataProvider ← FuelReadModel + FleetiConsumptionReadModel
    → GET /fuel/analytics (FuelImportController@analytics)
```

### 4.6 KPI registries

- **Operational `KpiRegistry`** (`app/Domain/Operations/KPI`): 21 definitions — OPS_001-008, FIN_100-102, FLT_200-203, DSP_300-301, MNT_400-401, HSE_500-501; each maps id → owner, calculator interface, read models, parameters, events, severity, command centers. Query-only metadata. ACTIVE.
- **BI `BusinessKpiRegistry`** (`app/Domain/Analytics/Registry`): 10 ACTIVE definitions (FLT_001-004, OPS_001-005, PRD_001) + 9 RESERVED enum ids awaiting dependencies (OPS_050/051, MNT_001, HSE_001, FIN_001-003, PRD_050/051 — fuel/driver read models, scoring rules, finance params). ACTIVE.

### 4.7 Policy graph (Fuel domain — decision ownership)

```
EdkImportParser (facts, no DB)
  → ParsedFuelImportFile/Row (+ ParseError)
FuelImportReferenceReader (query-only) → FuelImportReference
FuelImportClassifier (business FACTS only: TransactionType, FuelSource, ValidationFindings;
                      also businessFindingsFor() re-derivation used by review)
  → FuelTransactionClassification
ClassificationPolicy v1 (THE only decision owner)
  → PolicyOutcome {PersistenceDecision, KpiEligibility, ReviewDecision}
Consumers of the policy: FuelImportService (import+preview) · FuelReviewService (manual review re-decide)
```
Verified invariants (final acceptance audits, this branch): every persisted
`kpi_eligible/proposed_kpi_eligible/review_status/needs_review` originates from `PolicyOutcome`;
`ReviewOutcome` is audit-metadata only; proposal snapshot immutable; review events append-only;
`occurred_at` stabilized to non-mutating DATETIME.

---

## 5. Import Pipelines

### 5.1 EDK fuel import
`POST /fuel/import/edk/preview` → `FuelImportService::preview()` (no persistence, caches file 1h, token) →
`POST /fuel/import/edk/commit` → `FuelImportService::import()` in one DB transaction →
`fuel_import_batches` + `fuel_card_transactions` (accepted; proposal snapshot + effective values) +
`fuel_import_rejections` (quarantine, nothing lost). Idempotent via global-unique `transaction_ref`.
CLI twin: `fuel:import-edk` (thin, same service). Historical load done: 312 tx, Jan–Jul 2026, 84.56M FCFA.

### 5.2 Fleeti fuel workbook import
`POST /fuel/import/fleeti/preview` → `FleetiFuelParser` (layout-aware: Volume 2.0 / Carburant / legacy
Rapport; `_owned` column ownership) → commit → `FleetiImportService::persist()` (upsert by truck+date;
shared by HTTP commit and CLI `fuel:import-fleeti`). Historical load done: 238 daily records, May–Jun 2026.

### 5.3 Manual review loop
`/fuel/review` queue → `FuelReviewService::resolve()`: corrects facts (re-attribution), re-derives business
findings **via the classifier**, re-decides **via the policy**, appends immutable
`FuelTransactionReviewEvent`, never mutates the proposal snapshot.

### 5.4 Transport ticket import
`POST /transport_tracking/import` (Excel) + manual CRUD → on save: `TripSegmentBuilderService` (segments)
→ `RouteDeviationDetector`; `WeightGapDetector` on tracking save; nightly `ReconcileExpectedTickets`
matches GPS `expected_transport_tickets` ↔ tickets (±1 day).

---

## 6. Dashboard Pipelines (current state — pre-migration)

| Dashboard | Route | Pipeline | Data origin |
|---|---|---|---|
| Legacy admin Dashboard | `/dashboard` | HomeController → `DashboardDataService` + `FleetKpiService` (legacy direct-query trio) | models directly |
| Role dashboards | `/dashboard` (role-routed) | Driver/Hse/LogisticsResponsible pages | controller queries |
| Executive Command Center | `/dashboard/executive` | frozen L1→L6 pipeline (§4.3) | Read Models |
| Operations Command Center | `/dashboard/operations` | same, OperationsTranslator | Read Models |
| BI Executive/Operations/Fleet | `/business/*` | BusinessKpiRegistry pipeline (§4.5) + exports | Read Models via metrics calculators |
| Transport dashboard/reports | `/transport_tracking/dashboard`, `analytics/*` | TrackingDashboardController + AI panel | controller queries |
| Fuel analytics (descriptive) | `/fuel/analytics` (JSON) | FuelDashboardDataProvider | Fuel read models |

Duplication noted in ROADMAP (§ Réalisation/KPIs) between legacy KPI services and calculators is a
**documented migration-in-progress state** (R1.3 shims), recorded here as fact.

---

## 7. Services Layer — ownership & liveness (full detail in agent report)

**Fleeti intake stack (ACTIVE, scheduled):** FleetiSyncService (orchestrator) → FleetiService (API),
TelemetrySnapshotService, KilometerService (+MaintenanceStatusService), EngineHoursService,
FuelTrackingService, FuelEventDetectorService, StopDetectorService → PlaceClassifierService; live mode adds
DailyDispatchEventDeriver, DispatchStatusResolver, DispatchEtaEstimator.

**Fuel (ACTIVE):** FuelImportService, FuelReviewService, FleetiImportService, EdkImportParser,
FleetiFuelParser, FuelComparisonService (reporting, TruckController).

**Theft suite (code-ACTIVE; presentation decommissioned in GPS-Decoupling Phase 1A; ROADMAP lists the
writers for Phase 1B removal):** TheftIncidentService hub ← FuelEventDetector, UnauthorizedStopDetector,
OffHoursMovementDetector (hourly cmd), RouteDeviationDetector, WeightGapDetector, UntrackedTripDetector
(manual cmd). Status recorded: **ACTIVE-WRITER / UI-ORPHANED (planned decommission)**.

**Trip/stop analysis (ACTIVE):** TripSegmentBuilderService, FreightLoopService, GeoService,
TicketReconciliationService.

**KPI trio (ACTIVE-LEGACY, migration source):** TruckKpiService, FleetKpiService, DriverKpiService —
feed `/dashboard`, truck/driver pages; partially delegated to Calculators (R1.3).

**Planning/optimization:** FleetCapacityService, FleetOptimizerService, FleetObjectiveService,
AvailabilityService, RestWindowPlannerService, RotationAchievementService, DispatchWorkspaceService,
PlanningWorkspaceService, OperationsCalendarService, ObjectiveTargetResolver, ObjectiveHistoryService —
ACTIVE via OperationsController/FleetOptimizationController/DailyDispatchController.

**Integrations (ACTIVE):** SharePointStorageService (+ SyncDocumentToSharePoint job),
SharePointChecklistService, SharePointDailyChecklistService, WhatsApp DispatchNotifier/WhatsappClient
(+ SendDispatchWhatsappJob), OperationalParameterService.

**Watch-list (low/zero non-test callers):** DashboardDataService (single caller: HomeController legacy
dashboard) — candidate for the audit's attention, recorded without judgment.

---

## 8. Persistence Ownership (write-owner per table)

| Table | Write owner(s) |
|---|---|
| truck_telemetry_snapshots | TelemetrySnapshotService (+ telemetry:compact pruning) |
| kilometer_trackings / engine_hour_trackings / fuel_trackings | Kilometer / EngineHours / FuelTracking services |
| fuel_events | FuelEventDetectorService |
| truck_stops | StopDetectorService (+ PlaceClassifierService classification update) |
| trip_segments | TripSegmentBuilderService |
| theft_incidents | TheftIncidentService (sole writer; 6 detector feeders) |
| fuel_card_transactions / fuel_import_rejections / fuel_import_batches | FuelImportService (import) + FuelReviewService (effective fields only) |
| fuel_transaction_review_events | FuelReviewService (append-only) |
| fleeti_daily_records | FleetiImportService (upsert by truck+date) |
| daily_dispatches / daily_dispatch_events | DailyDispatchController + DispatchStatusResolver/EtaEstimator / DailyDispatchEventDeriver |
| expected_transport_tickets | TicketReconciliationService (+ dispatch creation) |
| transport_trackings | TransportTrackingController (CRUD) + import |
| maintenances / truck_maintenance_profiles | MaintenanceController + TruckMaintenanceService / MaintenanceStatusService |
| inspection_checklists (+issues) | LogisticsInspectionController; HSE sign; LogisticsManager validation |
| trucks | TruckController CRUD + live-cache (TelemetrySnapshotService) + odometer (KilometerService) |
| places | DetectPlaceHubs command (auto) + PlaceController (admin) |
| logistics_alerts | FuelEventDetectorService, TheftIncidentService, NotifyDueEngineMaintenance |
| documents | upload controllers + SyncDocumentToSharePoint |
| users / invitations / roles / permissions | User/Invitation/Role controllers + seeders |
| operational_parameters / fleet_settings | OperationalParameterService seeder+service / FleetSettingsController |

---

## 9. Frontend Knowledge Graph (summary; full inventory in agent report)

- **89 pages**, all ACTIVE with controller `Inertia::render` owners; drawer components are page-internal (not page loads).
- **Navigation** (`layouts/Sidebar.tsx`): permission-gated sections — Opérations (Planification/Répartition/Transports/Réalisation/Réconciliation/Carburant/Revue carburant), Ressources (Camions/Conducteurs/Affectations), Maintenance, Conformité (Inspections), Administration (Paramètres/Utilisateurs/Rôles/Journal), Compte; Driver-role isolated self-service (Checklist/Issues/Mes voyages/Mon camion). localStorage-persisted sections; badge counts (reconciliation missing).
- **Patterns:** `useWorkspaceDrawer` URL-driven drawers (users, roles, providers, transporters); dual-drawer Detail→Form (drivers, trucks, transport, fuel, inspections); multi-tab workspaces (maintenance board/history/rules; fuel edk/fleeti); deep links `?view/?edit/?create`.
- **AJAX surface:** 40+ endpoints via `apiFetch` (CSRF wrapper) — edit-data fetches, toggles, document upload/delete, fuel preview/commit/review, maintenance approve, optimization run, notifications, ask-ai.
- **Shared kit:** DataTable, Drawer, Modal, Badge, Button, Card, FormInput/Select/Checkbox/Textarea, Tabs, Pagination, ConfirmDialog, Toast, PageHeader, DetailPanel, EmptyState…; dashboard kit (KpiCard/KpiGrid/RatioCard/TonnageChart/TopList/PeriodFilter/AlertBanner/InsightCard); BusinessReport (shared by 3 BI pages); ConclusionCard (command centers); LeafletMap; hooks usePermission/useWorkspaceDrawer/usePolling/useExport/useTheme.
- **Dormant frontend:** `FilterBar` + `useFilters` (built, unreferenced by major pages).

---

## 10. Consolidated Liveness Register

**ACTIVE (production paths):** everything in §4–§9 not listed below.

**DORMANT (built, no runtime consumer — deferred features, recorded as fact):**
- Domain: `FuelCalculator`; Translators Dispatch/Fleet/Finance/Maintenance/HSE; 9 RESERVED BusinessKpiIds.
- Permissions: `maintenance-assign`, `maintenance-delete`, `rotation-validate`, `user-show`, `user-change-password`, `role-show`, `invitation-show`, `inspection-*` (strings seeded, not checked in code).
- Frontend: `FilterBar`, `useFilters`.
- Schema: `fuel_card_transactions.review_note` (notes live on review events); `fuel_import_rejections.needs_review` (persisted, not yet surfaced).
- Laravel scaffolding: ForgotPassword/ResetPassword/Verification/ConfirmPassword controllers (register+reset disabled in `Auth::routes`).

**ACTIVE-WRITER / UI-ORPHANED (decommission planned — ROADMAP GPS Phase 1B):** theft detector suite +
`theft_incidents` writers (presentation removed in Phase 1A; writers still scheduled/invoked).

**ACTIVE-LEGACY (documented migration source, R1.3):** TruckKpiService, FleetKpiService,
DriverKpiService, DashboardDataService (legacy `/dashboard` pipeline).

**Console commands, manual-only (operational tooling):** AnalyzeFleetStops, DetectUntrackedTrips,
DiscoverFleetiPlaces, ImportEdkHistory, ImportFleetiHistory, ImportFleetiGeofences,
InspectRotationAchievement, ListLocations, SeedInspectionTestData, StopsFromFleeti, StopsWhere,
SyncOperationalParameters.

---

## 11. Scheduler Map (bootstrap/app.php)

| Cadence | Command |
|---|---|
| every minute | `queue:work database --stop-when-empty` |
| 06:00–07:59 every min / working hrs every 2 min / night every 5 min | `fleeti:sync-live-dispatch --cadence=1/2/5` |
| every 30 min | `fleeti:sync-kilometers` |
| every 15 min | `logistics:notify-due-engine-maintenance` |
| hourly | `logistics:detect-off-hours-movement` |
| daily 02:30 / 02:45 / 23:00 | `places:detect-hubs` / `logistics:rebuild-trip-segments` / `logistics:reconcile-expected-tickets` |
| Mondays 07:00 | `logistics:notify-missing-weekly-checklists` |
| monthly 1st 03:15 | `telemetry:compact` |

All also triggerable via token-gated `GET /cron/run/{slug}` for shared hosting.

---

## 12. Cross-Reference Index (who-calls-what, key hubs)

| Hub | Called by | Calls |
|---|---|---|
| `FleetiSyncService` | 2 scheduled commands | 10 intake/derivation services (§7) |
| `TheftIncidentService` | 6 detectors | theft_incidents, logistics_alerts |
| `ClassificationPolicy` | FuelImportService, FuelReviewService | (pure) |
| `FuelImportClassifier` | FuelImportService, FuelReviewService | FuelImportReference |
| `OperationalParameterService` | 5 calculators + seeder + tests | operational_parameters |
| `KpiRegistry` | OperationalIntelligence | (metadata only) |
| `BusinessKpiRegistry` | 3 BI command centers, ExportController | (metadata only) |
| `TransportTrackingReadModel` | 2 calculators, 1 deriver, 2 BI metrics calculators | transport_trackings |
| `DerivedBusinessEventSource` | 2 operational command centers | 4 derivers |
| `usePermission()` (frontend) | Sidebar + all index pages | Inertia shared auth props |

Full per-class caller/callee entries: agent container reports (Domain: 212 files · Services/Models ·
HTTP/auth · Frontend), synthesized above; per-file detail retained in section tables of this document
and the four source explorations.

---

*End of knowledge graph. This document is descriptive only — it contains no findings,
recommendations, or fixes. It is the agreed foundation for the forthcoming Enterprise Architecture
Audit preceding the dashboard migration.*
