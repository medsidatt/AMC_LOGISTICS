# AMC Fleet Platform — Roadmap

> Single source of truth for project status. **Update this file before every
> commit** that completes a feature, refactor, cleanup, or infra change:
> mark phases done, record the completing commit hash, add/remove technical
> debt, and refresh *Current Focus* / *Next Phase*. Keep it concise.

**Last updated:** 2026-06-29

---

## Current Focus
**`develop` integration complete (2026-06-29).** All SPA workspaces — Trucks, Transport Tracking, Drivers, Fuel, Maintenance, Inspections (Fleet) + Roles & Users (Administration) on the shared `useWorkspaceDrawer` URL-driven standard — plus **GPS Infrastructure Decoupling** and **SharePoint Step 2/3** (local-first background upload) are now consolidated on `develop`.

**Production Phase 1** — **Step 1 (WhatsApp dispatch)** on `main`, **Waiting for Production Validation** (gate CLOSED). **Step 2/3 (SharePoint upload)** — implemented + locally verified (local-first + `sync_status` lifecycle; Driver/Maintenance `Document` devis via `Document::storeLocalAndQueueSync()`); on `develop`, deployment to `main` gated on Step 1 approval.

## Next Phase
Open Step 1's production gate → deploy Step 2 → then Step 3 (serialized). Administration SPA continues: Providers → Transporters → Entities → Projects. (Excel import dropped — orphaned legacy; OpenAI → P1.5.)

## Standard integration pattern (platform rule)
Every external provider (SharePoint, Office365, WhatsApp, future SMS/AI/APIs): **persist locally first → mark sync state → queue the external sync → retry automatically → never lose local data when the provider is down → expose the sync state for ops.** First implemented for documents (`Document.sync_status`, `SyncDocumentToSharePoint`).

---

## Track A — Operations Platform
`Planning → Dispatch → Réalisation → Réconciliation → Analytics → Optimization`

| Area | Status | Milestone (commit) |
|---|---|---|
| Planning · Operations Calendar | ✅ | `f643a929` |
| Planning · Per-truck capacity model | ✅ | `e2d9f064` |
| Planning · Availability windows + engine | ✅ | `65177c7c` |
| Planning · Flat workflow nav (Planification→Répartition→Réalisation→Réconciliation) | ✅ | `bcfc99d1` |
| Planning · Full objective hierarchy on overview (no winner-takes-all) | ✅ | `d792d57a` |
| Planning · Confirm-before-overwrite + archived-objective reactivation | ✅ | `d057b3c8`, `effa1df7` |
| Réalisation · Redesign (operational briefing, per-truck realization) | ✅ | `bcfc99d1` |
| Réalisation · Hierarchical-mean reference estimation | ✅ | `04857584` |
| Réconciliation · Missing-ticket worklist + nightly reconcile | ✅ | (existing) |
| Analytics · Real `suspiciousDrivers` metric (de-fabricated) | ✅ | `39730420` |
| Opérations · **Transports** sidebar entry (ticket system of record) + canonical-link fix (`/transport_trackings/*` 404s) | ✅ | *(nav branch)* |
| **GPS Infrastructure Decoupling · Phase 1A** — remove GPS *presentation* (Live Tracking, Fleet Map, Theft pages, Trip Replay, Idle report); GPS kept as silent feed for Maintenance/Fuel/Réconciliation (split `FleetiSyncService`, kept stop→place→ExpectedTicket chain) | ✅ | `feature/gps-infra-decoupling` |
| **Providers SPA workspace** (Administration — Phase 4.3, standardization only) — Modals → Drawers via shared `useWorkspaceDrawer`; `ProviderFormDrawer` (create+edit) + `ProviderDetailsDrawer` (`DetailPanel`); `PageHeader`; removed 3 dead routes (`providers.show/create/edit` — no controller methods) + dead `resources/views/pages/providers/` Blade dir + unused `router` import; controller middleware trimmed to existing methods; **zero business-logic/validation/permission/schema changes** | ✅ | `develop` |
| **Transporters SPA workspace + Counterparty extraction** (Administration — Phase 4.4) — Modals → Drawers; **first proven shared extraction**: `CounterpartyFormDrawer` + `CounterpartyDetailsDrawer` (`components/counterparty/`) + `CounterpartyRules` (backend validation helper, unique-name opt-in) now used by BOTH Providers & Transporters; Provider drawers deleted (refactored onto shared); removed 3 dead transporter routes + dead `views/pages/transporters/` Blade; entity-specific kept (Transporter name non-unique, Provider unique); **zero business-logic/schema change** | ✅ | `develop` |
| **Transport Tracking · Operational visibility** (Phase 5.3A — visibility only) — per-row operational status on the ticket list (Complet / Incomplet / Écart / Sans pièce / Sync…) + status filter chips (incomplete / anomaly / no-attachment / missing provider-or-client weights / unsynced) + **"chargements GPS sans ticket" banner** (open `ExpectedTransportTicket`, one-click Créer ticket via the existing create flow). All flags **reuse existing definitions** (`MissingTransportTrackingExport` null-set, `weightGapThreshold()`, `Document::SYNC_*`, `ExpectedTransportTicket::open`) — **no new business rules, no form/schema change**. Operators now see what needs attention without opening Réconciliation | ✅ | `develop` |
| Planning · PeriodSwitcher on the overview (historical periods) | 🟡 backlog | — |
| Optimization · (rotation/route optimization) | ⚪ future | — |

## Track B — Infrastructure
`Production Deployment · Queues · Scheduler · Caching · Monitoring · Performance · Security`

| Area | Status | Milestone (commit) |
|---|---|---|
| Deployment · Committed build artifacts (Infomaniak, git-pull only) | ✅ | `a316fc29`, `af98ee45` |
| Build · `type-check` script + tsconfig fix | ✅ | `51672fb0` |
| Security · User suspension enforced server-side | ✅ | `98e6f30b` |
| Security · Phantom truck/driver guard (data integrity) | ✅ | `7b4d54d7` |
| Config · Safe `.env.example` + `DEPLOYMENT.md` | ✅ | `2ad1a259` |
| Queues · `database` driver + **cron-driven worker** (`queue:work` scheduled) | ✅ infra | `2c499bea` |
| Queues · Step 1 WhatsApp dispatch async (timeout, logging, idempotent) | ✅ code · ⏳ prod-validation | `2c499bea` |
| Queues · Step 2 **SharePoint Background Upload** (active bottleneck) | 🟠 architecture review | — |
| Queues · ~~Excel import~~ | ❌ dropped — orphaned legacy (→ housekeeping removal) | — |
| Queues · Step 3 — re-audit next bottleneck (not reserved) | ⚪ later | — |
| Scheduler · cron entry on server (`schedule:run`) | 🟠 pending (server) | — |
| Test infra · dedicated test DB + CI pipeline | 🟠 pending | — |
| Security · rotate leaked mail password + `AJAX_TOKEN`; drop hardcoded fallback | 🟠 pending (server) | — |
| Production `.env` · `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true` | 🟠 pending (server) | — |
| Caching · Redis for cache + sessions | ⚪ future | — |
| Monitoring · error/uptime/observability | ⚪ future | — |
| Performance · JS code-splitting (>500 KB chunk) | ⚪ future | — |

## Track C — Platform Architecture
`Shared Services · Authentication · Notifications · Documents · Email · SMS · WhatsApp · SharePoint · MCP · API Gateway`

| Area | Status | Notes |
|---|---|---|
| Authentication · Spatie permissions + Microsoft OAuth/SSO + suspension | ✅ mature | `98e6f30b` |
| Shared Services · Capacity / Achievement / Workspace / FleetIdentifier | ✅ mature | — |
| Notifications · in-app + WhatsApp dispatch job | ✅ | `SendDispatchWhatsappJob` |
| Documents · SharePoint storage + DomPDF | ✅ | — |
| Email · Office365 SMTP (InvitationMail) | ✅ sync | queue when Track B·Queues lands |
| WhatsApp · Business Cloud API (Meta) | 🟡 gated | needs Meta template/sender approval |
| SharePoint · Graph file access | ✅ | — |
| SMS | ⚪ not present | — |
| MCP / API Gateway · Sanctum + Swagger REST | ✅ basic | MCP not present |

---

## Deferred Backlog (revisit when multi-project is a real requirement)
- **Entities, Projects, Places** (Administration master-data) — **deferred 2026-06-29**. The system runs a single project, so these give little operational value now. Entities = logo + registration IDs; Projects = dedicated page (user-assignment workflow); Places = Operations geo-master-data. Return to them when multi-project support is needed.

## Operational phases (5.x) — candidate pool (audited 2026-06-29, next phase chosen by operational impact)
- **Transport Tracking** — daily system of record; **under-ticketing gap** (~20 missing loads/month, 30–40% miss at some quarries) corrupts billing + KPIs + theft signals; missing-ticket flag only on `/reconciliation`, not the operator's list. *(Recommended next — highest operational impact, low scope.)*
- **Dispatch/Planning** — plan↔dispatch disconnect (`TruckAssignment` invisible to the dispatch board; crew not checked); **target-resolution logic duplicated ×3–4** (`FleetObjectiveService` / `FleetCapacityService.resolveWeeklyTarget` / `ObjectiveTargetResolver` / `RotationAchievementService`). High value, higher effort/risk (optimizer).
- **Réalisation/KPIs** — heavy duplication: weight-gap threshold inlined ×3 (ignores `TransportTracking::weightGapThreshold()`), discipline score ×2 (~40 lines), `averageCycleDays` ×2, fuel-yield ×4, load-rate ×3, default-capacity ×4, monthly-tonnage grouping ×2, `TonnageChart` ×3+. Low-risk de-dup; prevents divergent dashboard numbers.
- **Maintenance** — already mature; **Product Catalog integration is complete end-to-end** (only gap: no catalog admin screen). Remaining = depth/polish (forecast calendar, cost-in-history, assignment workflow). Lowest urgency.

## Technical Debt
- **GPS Decoupling · Phase 1B — backend theft decommission** (deferred follow-up to 1A). After 1A the theft layer is invisible (no pages, no dashboard widgets, no escalation in `FleetiSyncService`) but the writers still exist. Remove now-orphaned `UnauthorizedStopDetector`, `OffHoursMovementDetector`, `UntrackedTripDetector`, `RouteDeviationDetector`, `WeightGapDetector`, `TheftIncidentService`; **split** `FuelEventDetectorService` (keep fuel events, drop theft write) and `TripSegmentBuilderService` (drop `routeDeviationDetector`); strip the `runTheftDetectionHooks` WeightGap call in `TransportTrackingController`; drop the `theft_incidents` table + the theft commands. Reference-check each first — `FreightLoopService`/`TripSegmentBuilderService` are shared with Réalisation/Réconciliation.
- **Analytics consolidation (Phase 2)** — `TrackingDashboardController` (`/dashboard/{trackings,fleeti,rotations}`) backs the live Analytics tabs (`AnalyticsTabs`, `analytics/Rotations`, `transport-trackings/Reports`); it is *not* orphaned. Fold into Réalisation per the dashboard-consolidation plan, then remove. (Corrected an earlier audit false-positive.)
- Vestigial `fleet-map-view` permission row remains in the DB from migration `2026_06_16_140000` (removed from the catalog; no route checks it). Optional cleanup migration to drop it.
- **Housekeeping · Remove legacy transport-tracking Excel import** (orphaned; no React/nav/route consumer — depends on legacy `saveForm()` jQuery the Inertia app no longer loads). Remove: the 2 `transport_tracking/import` routes, `TransportTrackingController@import`, `resources/views/pages/transport_trackings/import.blade.php`, the `TransportTrackingImport` importer (only caller), and the now-unused legacy JS dependency. **Do NOT remove now** — a future housekeeping phase, after verifying no external/direct-URL consumers. *[found Phase 1 re-audit]*
- **Orphaned `TransportDashboard`** (`/transport_tracking/dashboard`) — no live link; overlaps Réalisation's achievement KPIs. Per the workflow split (KPIs → Réalisation), fold its unique charts (monthly tonnage, timeline gantt) into Réalisation/Analytics, then remove the page + route + `dashboard()` method. *[found Operations nav audit]*
- **Legacy Blade nav** (`main.blade.php` / `welcome.blade.php` / `navigation/sidebar.blade.php`) is dead (Inertia renders via `app.blade.php` + `Sidebar.tsx`) — housekeeping removal. *[found Operations nav audit]*
- `config/app.php` ships a hardcoded `AJAX_TOKEN` fallback (`MySecretToken123`) — remove once prod sets a real token.
- Tests run against the **live dev DB** via `DatabaseTransactions` (no separate test DB).
- `operationsBadges` runs a count query per request for dispatch users — add caching.
- `redistributeOpenObjectives()` iterates objectives without an `active()` filter (touches archived rows) — review.
- Weight-gap threshold fetched inline in `DriverKpiService`/`TruckKpiService`/`FleetKpiService` — consolidate to `TransportTracking::weightGapThreshold()` (reconcile `?:` vs `??` for a 0 threshold; no KPI test coverage yet). *[found H1]*
- Folder structure: `logistics/objectives/Create` + `logistics/planning/Weekly` belong to the operations workflow but live under `logistics/` — relocate to `operations/` (cosmetic; churns render names + build). *[found H2]*
- UI: convert hand-rolled forms (`logistics/places/{Create,Edit}`) + raw `<textarea>` (`trucks/{Create,Edit}`, `logistics/demands/Create`) to `Form*` components — needs visual verification. *[found H3]*
- UI: `dashboard/PeriodFilter` raw toggle buttons → shared `Button`/a `PeriodControl` wrapper. *[found H3]*
- UI: hardcoded `text-red-500` (#ef4444) vs `--color-danger` (#ea5455) across ~19 files — standardize the danger token (deliberate; shifts shade). *[found H3]*
- **Phase 1.5 — Async AI analysis** (deferred; OpenAI stays synchronous until delivered as a *complete* experience — do not ship a partial async AI). Must include: analysis **result storage** (persistent history), **job status tracking**, **polling or WebSocket** result delivery, retry/failure handling, and **frontend progress states**. Reuse the existing OpenAI prompt logic in `TransportTrackingController@analyze/analyzeAll`; move only orchestration into a job.
- Analytics · Audit-log enhancements (from removed PLAN-NOTE): dedicated detail page `/admin/audit-logs/{log}`; deep-link Subject cell to the resource Show; reusable `AuditTrail` component for Show pages; multi-action filter selector.
- Untracked `docs/audit/*` and `docs/backlog/*` — decide commit vs discard.

## Resolved Debt
- **H3 UI consistency**: audit confirmed broad consistency (shared component library used throughout); removed a dead `PLAN-NOTE` comment block (`admin/AuditLogs.tsx`), ideas preserved in backlog above. Larger form/button conversions deferred (need visual verification; color changes excluded).
- **H1 dead-code sweep**: removed orphan `RequestExplanation` mail, 3 dead commented blocks (old `dashboard()`, `isExpired()`, `getGapAttribute()`), and 5 orphan frontend files (`AcceptInvitation` page, `Can`/`QuickFilters`/`AchievementSummary`/`FormFileUpload` components). Verified 0 references; tsc/build/tests green.
- Build artifacts tracked then mis-ignored → settled on committed-artifacts strategy (`af98ee45`).
- Fabricated dashboard metric (`suspiciousDrivers: 0`) → real calculation (`39730420`).
- Stale planning tests vs flat nav → updated (`d1236463`).
- Objective overwrite silently replacing manual planning → confirm-before-overwrite (`d057b3c8`).
