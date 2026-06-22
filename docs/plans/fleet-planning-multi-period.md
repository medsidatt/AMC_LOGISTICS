# Fleet Planning — Multi-Period Objectives & Scoreboard

_Plan owner: Logistics Responsible role. Created 2026-06-22._

**Status (2026-06-22):** Phases 1–3 implemented & verified (backend resolution, scoreboard UI, objective authoring CRUD). Phase 4 polish partially done (source/coverage badges, timeline). Deferred per §7: alternate objective units (km) and Driver/Route scope.

## 1. Goal

Turn the weekly-only planning scoreboard (`/logistics/planning/weekly`) into a
**period-aware** scoreboard supporting **Week / Month / Year / Custom range**,
backed by **multi-level objectives** with **hierarchical target resolution** so
that Planned / Actual / Remaining / Achievement % are always available for any
selected period.

No "Day" mode — daily tracking stays in Operations/Dispatch (`/logistics/planning`)
and KPI monitoring. Planning is tactical/strategic (Week, Month, Year, Custom).

## 2. Current state (verified in code)

- `fleet_objectives`: one row per period, `unique(start_date, end_date)`,
  columns `target_tons`, `target_rotations`, `working_trucks`, `rested_trucks`.
  **No `period_type`** — periods are implicitly weekly (Mon→Sat), created when a
  roster is applied (`FleetObjectiveService::upsert()`).
- `fleet_objective_trucks`: frozen per-truck snapshot (`target_rotations`,
  `target_tons`, `capacity_tonnage`).
- Reporting: `RotationAchievementService::forPeriod($start,$end)` —
  **realized side is already period-agnostic** (tickets by `client_date` range,
  GPS loops by range, projection from day diff). **Target side is broken for
  non-weekly periods**: it exact-matches one `FleetObjective` on
  `(start_date,end_date)` (`RotationAchievementService.php:70-73`), so month/year/
  custom ranges find no objective → Planned = 0.
- Units are **rotations + tonnage** (not km). Scope is **fleet + per-truck**.
- All planning screens are gated by permission `daily-dispatch-list` (Logistics role).

## 3. Target resolution rules (the core requirement)

Objectives carry a `period_type` ∈ {WEEK, MONTH, YEAR, CUSTOM}. For a selected
view period, resolve the target by **most-specific-first with fallback**:

| View   | Resolution order                          |
|--------|-------------------------------------------|
| Week   | Weekly → Monthly → Yearly (prorated)      |
| Month  | Monthly → Yearly (prorated) → Σ Weekly    |
| Year   | Yearly → Σ Monthly                        |
| Custom | Σ overlapping objectives by date overlap  |

Rules:
1. Use the most specific objective that **contains** the selected period.
2. If none, derive from a broader objective by **date-overlap proration**
   (e.g. June target = annual × (June days ÷ year days)) — flagged as *derived*.
3. For custom ranges, sum overlapping objectives weighted by overlap.
4. The engine must **never return an empty target** purely because the range is
   not an exact weekly match.

The scoreboard always shows **Planned · Actual · Remaining · Achievement %**, and
labels whether the target was *exact*, *derived (prorated)*, or *aggregated*.

## 4. Architecture (build on existing patterns)

- **`PlanningPeriodResolver`** (new service): `(mode, anchor) → {start, end, label}`.
  Week = Mon→Sat (unchanged), Month = calendar month, Year = calendar year,
  Custom = explicit start/end.
- **`ObjectiveTargetResolver`** (new service): `[start,end] → {target_rotations,
  target_tons, per_truck[], source: exact|derived|aggregated, coverage}` applying
  §3. Centralizes all hierarchy/proration logic.
- **`RotationAchievementService`**: replace the inline exact-match objective lookup
  in `computePeriod()` with a call to `ObjectiveTargetResolver`. For an exact
  weekly match the resolver returns the identical row → **existing weekly view,
  dashboard, and roster history are unchanged** (regression-safe by construction).
- **`DailyDispatchController`**: make the scoreboard action period-aware
  (`mode`, `anchor`/`start`/`end`); keep `/logistics/planning/weekly` working
  (default `mode=week`).
- **UI**: a segmented period switcher on the scoreboard page; reuse
  `AchievementSummary`, the per-truck table, fill-rate card, missing-tickets list
  as-is (already period-agnostic).

## 5. Data model change

`fleet_objectives`:
- Add `period_type` ENUM('WEEK','MONTH','YEAR','CUSTOM') NOT NULL DEFAULT 'WEEK'.
- Backfill existing rows → 'WEEK'.
- Replace `unique(start_date,end_date)` with `unique(period_type,start_date,end_date)`
  so a week and the month containing it can coexist.

No change to `fleet_objective_trucks` (per-truck snapshots stay; year/month
objectives may have a fleet-level target with optional per-truck distribution).

## 6. Delivery phases

- **Phase 1 — Foundation (this change):** migration + model (`period_type`,
  constants, scopes), `FleetObjectiveService::upsert()` writes `period_type`
  (default WEEK, preserves behavior), `PlanningPeriodResolver` +
  `ObjectiveTargetResolver`, wire resolver into `RotationAchievementService`.
- **Phase 2 — Scoreboard UI:** period switcher (Week/Month/Year/Custom),
  per-mode navigation, controller params, deep-linkable URLs.
- **Phase 3 — Objective authoring:** UI to create Month / Year / Custom
  objectives (today only weekly objectives are auto-created on roster apply).
- **Phase 4 — Polish:** "derived/aggregated target" labels, partial-coverage
  states, year view monthly roll-up.

## 7. Explicitly deferred — needs a product decision (NOT assumed)

These appear in the spec but do not fit the current domain and are large; they
are **out of Phases 1–4** until decided:
- **Objective Type / alternate units** (e.g. distance km). The app's objective
  unit is rotations + tonnage; introducing km targets is a new metric pipeline.
- **Driver / Route scope.** Objectives are fleet + per-truck today. Driver-level
  and Route-level planning are new entities/relationships.

## 8. Risks & rollback

- **Migration** is additive + a unique-constraint swap; all existing rows become
  WEEK, so no duplicate-key risk. Reversible `down()`.
- **Reporting regression:** mitigated because the resolver returns the exact
  weekly row for existing weekly periods (verify weekly view + dashboard +
  roster history render identical numbers after the change).
- Rollback: `php artisan migrate:rollback` + `git revert` per file.
