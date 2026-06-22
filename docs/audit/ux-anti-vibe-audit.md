# Project-wide UX / Anti-Vibe Audit

_Created 2026-06-22. Identify-first; no cosmetic changes made. Evidence is grepped from the live codebase._

Scope: entire app (~80 screens). This is the analysis + prioritized plan; implementation follows approval, screen-batch by screen-batch.

---

## Deliverable 1 — UX Audit Report

### 1a. Implementation leakage (Phase 1) — confirmed instances (user-facing only; code comments excluded)
| File | Text | Verdict |
|---|---|---|
| trucks/Create.tsx, trucks/Edit.tsx | "Laisser vide pour utiliser le défaut flotte (…)" | **Leak** ("default value will be used") → drop; the placeholder already implies it |
| logistics/places/Index.tsx | "Le planificateur nocturne en créera automatiquement à partir de la télémétrie" | **Leak** (internal job) → "Lieux détectés par GPS" |
| logistics/affectations/Index.tsx | "il en sera retiré automatiquement" | **Borderline** — it's a business consequence; reword "Un chauffeur ne peut conduire qu'un camion à la fois" |
| logistics/theft-incidents/Index.tsx & Show.tsx | "identifier automatiquement les incidents", "les segments GPS seront automatiquement liés et l'incident clôturé" | **Leak** (how detection works) → state the outcome, not the mechanism |
| reports/IdleHourly.tsx | "…puis cliquez sur Prévisualiser" | **Leak** (UI mechanics) → drop |
| settings/FleetSettings.tsx | (already cleaned this session) | ✅ done |

Net: ~6 user-facing leaks remain (small, mechanical removals).

### 1b. Forms (Phase 3)
- **Spinners: 41 `type="number"` across 16 files** — every numeric form still uses the unsafe spinner (wheel/arrow/buttons), including my own new `logistics/objectives/Create.tsx`. The safe pattern (`NumberField`: text + inputMode + sanitise + wheel-blur) now exists only in `settings/FleetSettings.tsx`. → Promote `NumberField` to a shared `components/ui/` primitive and adopt across all 16 files.
- **Hidden auto-save: resolved** — the only `onBlur`→POST (the monthly-target row) was removed this session. No remaining hidden auto-save in `pages/`.
- **Consistency:** forms split between `useForm`+`FormInput` (good) and ad-hoc `<input>`/`<textarea>` (e.g. raw textareas in several pages). Standardise on `FormInput`/`FormTextarea`/`FormSelect`; one button row (`Annuler` secondary + primary), errors under field.

### 1c. Visual (Phase 8)
- **6 chart components**: VehicleUtilization, DistributionPie, MaintenanceGauge, RotationTimeline, WeightComparisonChart, TonnageChart. Audit each for "supports a decision" vs decorative (gauges/pies are the usual offenders) — per-dashboard review needed.
- Status badges/`Badge` now consistent (className+default added this session). Icon reuse remains (Users ×3, ClipboardCheck ×3) — low priority.

---

## Deliverable 2 — Navigation Audit Report

Current (post-restructure this session): 11 single-responsibility sections + role-aware `/dashboard`. Remaining issues:
- **Two logistics dashboards** — `LogisticsResponsibleDashboard` (role landing at `/dashboard`) **and** `LogisticsDashboard` (`/logistics/dashboard`, "Tableau logistique", now under Operations). Overlap; one should win.
- **11 flat sections** don't scale — needs **collapsible 2-level groups** + role-aware default landing (component change).
- Permission/domain mismatch: theft + geofences gated by `logistics-dashboard`.
- Recommended tree: as in `fleet-planning-engine-v2.md §9` extended app-wide (Planning · Operations & Dispatch · Transport · Fleet · Partners · Maintenance · Compliance & HSE · Telematics & Security · Fuel · Analytics · Configuration · Administration).

---

## Deliverable 3 — Duplication Report

| Module A | Module B | Duplication type | Recommendation |
|---|---|---|---|
| `LogisticsDashboard` (`/logistics/dashboard`) | `LogisticsResponsibleDashboard` (`/dashboard` role landing) | **Duplicate dashboard** | Pick one logistics overview; retire the other (likely keep the role-landing one) |
| `MonthlyTonnageTarget` (Fleet Settings) | `FleetObjective` (Objectives) | **Duplicate planning objective + KPI source** | Repoint KPI services → FleetObjective; drop MonthlyTonnageTarget (flagged) |
| `FleetKpiService::plannedTonnage` | `RotationAchievementService` target | **Duplicate "planned tonnage" engine** | Single planned-tonnage source = FleetObjective/resolver |
| "Modifications d'objectifs" (`ObjectiveHistory`) | "Journal d'activité" (`AuditLog`) | **Duplicate audit trail** (2 backends) | Consolidate to one immutable audit log; business "history" stays separate |
| "Tableau de planification" (scoreboard) | Logistics dashboard(s) | **Overlapping KPI surfaces** | Scoreboard owns objective attainment; logistics dashboards = operational status only |
| `fleet-roster` + `fleet-roster/history` | `Objectives` | Duplicate authoring + history | ✅ already merged/removed this session |
| `Analytiques` (TransportDashboard) | `Rapports` | Overlap insight vs export | Keep distinct under one Analytics domain (nav already groups them) |

---

## Deliverable 4 — KPI Audit Report

Two parallel KPI engines exist — the core duplication:
| Engine | Planned source | Done/actual | Owner | Consumers |
|---|---|---|---|---|
| `RotationAchievementService` | FleetObjective (resolver) | tickets + GPS | Planning | Planning Dashboard |
| `FleetKpiService` / `DriverKpiService` | **MonthlyTonnageTarget** (dup) | tickets | (unclear) | Fleet/Driver dashboards + scorecards |
| `TruckKpiService` | — | per-truck gap/fill | Fleet | Truck show, dashboards |

Flags:
- **Duplicate KPI**: "planned tonnage" computed two ways (different numbers possible). → single source.
- **Owner undefined**: FleetKpiService / DriverKpiService have no clear business owner documented.
- **Formulas to extract & document**: driver scorecard weights (risk of hardcoded/arbitrary weights — prior audit noted a "multi-factor scorecard"); gap-threshold KPIs. Each needs formula · source · meaning · owner · consumers documented (the RotationAchievement set is already documented in `fleet-planning-engine-v2.md §3`).
- **Decorative KPI risk**: per-dashboard KPI cards need the "supports a decision" test (Phase 6/8).

---

## Deliverable 5 — Interaction & Table Audit

- **Tables: 44 `DataTable` across 21 pages.** Each needs: primary action · secondary actions · decision supported · remove non-decision columns. (Per-table pass required — not yet done.)
- **Modals: 15 pages** use `Modal` for create/edit/confirm — consistent pattern (good). Check for dead-end flows + extra clicks per page.
- Filters/search/pagination are standardised via `DataTable` (searchable/exportable) — consistent. Audit for **unused filters** (the IA review flagged some).

---

## Deliverable 6 — Prioritized Fix Plan

**P0 — correctness / single-source (changes numbers; needs validation)**
1. Repoint `FleetKpiService` + `DriverKpiService` planned-tonnage → FleetObjective; then drop `MonthlyTonnageTarget` (finishes the Fleet-Settings de-dup). *(decision pending — flagged last turn)*
2. Resolve the two logistics dashboards (keep one).

**P1 — systemic UX (broad, mechanical, safe)**
3. Promote `NumberField` to `components/ui/`; replace all 41 `type="number"` spinners.
4. Remove the ~6 implementation-leak texts.
5. Consolidate the two audit trails (ObjectiveHistory vs AuditLog) — backend.

**P2 — structure / depth**
6. Collapsible 2-level sidebar + role-aware landing.
7. Per-table audit (44 tables): columns/actions/decision.
8. Per-dashboard KPI + chart audit: remove decorative cards/charts; document every surviving KPI (owner/formula/source/consumers).
9. Productivity Parameters + Operations Calendar (v2 phases) — new config, not cleanup.

Sequencing: P0 first (correctness), then P1 (sweep), then P2 (depth). Each batch: change → tests → build → review.
