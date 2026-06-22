# Fleet Planning Engine v2 — Enterprise Specification

_Status: design (awaiting approval to implement). Created 2026-06-22._
_Supersedes the calculation model in `fleet-planning-multi-period.md` (which remains valid for the period/hierarchy layer)._

This spec encodes eight ratified business decisions into a traceable engineering
design. Every KPI has a formula, denominator, data source, edge cases, and the
decision it supports. No magic numbers, no blended metrics, no calendar-day math.

---

## 1. Domain model & module ownership

Three business states, three owners — never cross-derived:

| State | Owner module | Meaning | Computed from |
|---|---|---|---|
| **Planned** | Objective (Planning) | The commitment | `planned_rotations × rated_capacity` |
| **Allocated** | Dispatch | Operational assignment decision | `TruckAssignment` records |
| **Actual** | Operations | Proven execution | Confirmed (ticketed/weighed) tonnage |

Capacity / Availability / Utilization are owned by the **Optimization engine**;
Planning **consumes** them. Confirmed-vs-estimated integrity is enforced everywhere.

**Primary users:** Fleet Manager (objectives, calendar), Dispatcher (assignments),
Operations (tickets/execution), Executive (read-only performance).

---

## 2. Data model

### 2.1 New / changed tables

**`trucks`** (add):
- `rated_capacity_tons` DECIMAL(8,2) NULL — real per-truck capacity. NULL ⇒ migration fallback to fleet default.
- `availability_factor` DECIMAL(4,3) DEFAULT 0.950 — planning assumption, fallback only.
- `maintenance_factor` DECIMAL(4,3) DEFAULT 0.980 — planning assumption, fallback only.
- (`target_rotations_per_week` already exists.)

**`operations_calendars`**: `id`, `name`, `is_default` BOOL, timestamps.
**`calendar_days`**: `id`, `calendar_id` FK, `date`, `day_type` ENUM(WORKING_DAY, HOLIDAY, SHUTDOWN, EXCEPTION), `note` NULL, **unique(calendar_id, date)**.
> Single global calendar in Phase 1; `calendar_id` carried on all planning calcs so multi-site is a later migration, not a redesign.

**`truck_availability_windows`**: `id`, `truck_id` FK, `start_date`, `end_date`, `type` ENUM(REST, MAINTENANCE, INSPECTION, BREAKDOWN, SHUTDOWN), `source` ENUM(MANUAL, SYSTEM), `note` NULL, timestamps.
> **Architecture reasoning — avoid data duplication.** The app already records downtime in `TruckRestWindow`, maintenance records, and inspections. We do **not** copy those. `AvailabilityService` **unions** existing sources (rest + maintenance + inspection) with this table (which captures only what isn't already modelled — BREAKDOWN, SHUTDOWN). Real events are always source of truth; static factors never override them.

**`fleet_objectives`** (add): `calendar_id` FK NULL (defaults to the default calendar). Keeps existing `period_type`, `archived_at`, `target_tons`.

**`fleet_objective_trucks`** (extend → per-truck allocation ledger): add `rated_capacity_tons` (snapshot), `proposed_rotations` INT, `final_rotations` INT, `allocation_source` ENUM(AUTO, MANUAL) DEFAULT AUTO, `override_reason` NULL. `planned_capacity = rated_capacity_tons × final_rotations` (stored for audit).
> Both the engine proposal and the human decision are preserved permanently for planning-quality analysis (audit requirement).

### 2.2 Reused (no change)
- `TruckAssignment` (`planned_tonnage`, `planned_date`, `status`) → Allocated Capacity.
- `transport_trackings` (`client_net_weight`, `client_date`) → Confirmed tonnage.
- GPS freight loops → Estimated tonnage.

---

## 3. Calculation engine — full formula register

> Operational days come from the calendar: `WORKING_DAY` only (HOLIDAY/SHUTDOWN/EXCEPTION excluded). Calendar-day math is a fallback only when no calendar is configured.

### 3.1 Capacity & availability
| KPI | Formula | Denominator / source | Edge cases |
|---|---|---|---|
| Rated Capacity (truck) | `rated_capacity_tons` | trucks; NULL → fleet default (migration only) | NULL flagged in UI as "unconfigured" |
| Planned Capacity (truck) | `rated_capacity_tons × final_rotations` | objective allocation | rotations 0 → 0 |
| Fleet Planned Capacity | `Σ truck Planned Capacity` | — | — |
| Operational Rotations (truck, period) | `target_rotations_per_period × operational_working_days_ratio` | calendar | 0 working days → 0 |
| Lost Rotations | derived from availability windows ∩ period (rest+maint+inspection+breakdown+shutdown) | AvailabilityService | overlapping windows de-duplicated by day |
| Available Rotations | `Operational Rotations − Lost Rotations` (real); else `target_rotations × availability_factor × maintenance_factor` (fallback) | windows → factors | new truck, no windows → factors |
| Available Capacity | `rated_capacity_tons × Available Rotations` | — | — |
| Lost Capacity | `rated_capacity_tons × Lost Rotations` | — | reported by downtime category |
| Availability % | `Available Capacity ÷ Planned Capacity` | Planned | Planned 0 → null |

### 3.2 Execution & integrity (confirmed vs estimated — never blended)
| KPI | Formula | Source | Edge cases |
|---|---|---|---|
| Confirmed Capacity | `Σ client_net_weight` (ticketed/weighed) | transport_trackings | the **only** input to official Achievement |
| Estimated Capacity | `Σ gps_only_loops × rated_capacity` | GPS loops | informational only |
| Total Operational Activity | `Confirmed + Estimated` | — | — |
| **Achievement %** | `Confirmed ÷ Planned Capacity` | — | Planned 0 → null; **never** uses estimated |
| Estimated Coverage % | `Estimated ÷ Planned Capacity` | — | — |
| Data Quality % | `Confirmed ÷ (Confirmed + Estimated)` | — | denominator 0 → null |
| Fill Rate (operational KPI) | `avg confirmed load ÷ rated_capacity` | weighed only | excludes GPS |

### 3.3 Allocation & utilization (Dispatch source of truth)
| KPI | Formula | Source | Edge cases |
|---|---|---|---|
| Allocated Capacity | `Σ TruckAssignment.planned_tonnage` | dispatch | no assignments → 0 |
| Planning Adherence % | `Allocated ÷ Planned` | — | over-allocation → >100% (shown, not capped) |
| Execution Achievement % | `Confirmed (Actual) ÷ Allocated` | — | Allocated 0 → null |
| Objective Achievement % | `Confirmed ÷ Planned` | — | = Achievement % |
| Utilization % | `Confirmed ÷ Available` | — | Available 0 → null |
| Allocation Efficiency % | `Allocated ÷ Available` | — | — |

### 3.4 Pacing & projection (operational days)
| KPI | Formula |
|---|---|
| Target Pace | `Period Target ÷ Total Operational Days` |
| Expected Progress | `Operational Days Elapsed ÷ Total Operational Days` |
| Projected Achievement | `(Confirmed ÷ Operational Days Elapsed) × Total Operational Days` |
| On Track | `Projected ≥ Planned` (guarded: needs ≥ N elapsed operational days, else "insufficient data") |

### 3.5 Manager decision matrix (designed-in, not decorative)
| Signal | Reading |
|---|---|
| Low Achievement + High Utilization | Capacity shortage → add trucks/capacity |
| Low Achievement + Low Utilization | Under-assignment → dispatch problem |
| High Allocation + Low Achievement | Execution problem on the ground |
| Allocated < Planned | Under-allocation |
| Actual > Planned | Target exceeded |

---

## 4. Workflows

1. **Objective authoring (Fleet Manager).** Enter target tonnage + period + calendar → engine **proposes** per-truck `proposed_rotations` (capacity- and availability- and calendar-aware) → manager **overrides** (`final_rotations`, `override_reason`) → save. Proposal retained forever (`allocation_source`, audit).
2. **Dispatch (Dispatcher).** Daily `TruckAssignment` rows → Allocated Capacity.
3. **Execution (Operations).** Tickets → Confirmed; GPS-only → Estimated.
4. **Review (Executive).** Two-section dashboard (below).

---

## 5. Permissions & responsibilities
| Capability | Permission | Role |
|---|---|---|
| Manage operations calendar | `operations-calendar-manage` (new) | Fleet Manager |
| Create/edit objectives + allocations | `fleet-roster-plan` (exists) | Fleet Manager |
| Manage availability windows | `truck-availability-manage` (new) | Fleet Manager / Maintenance |
| Dispatch assignments | `daily-dispatch-edit` (exists) | Dispatcher |
| View planning dashboards | `daily-dispatch-list` (exists) | All planning roles + Executive |

---

## 6. Edge cases (must be handled & tested)
Holidays / shutdowns in period · new truck with no windows (→ factors) · zero available rotations · over-allocation (>100%) · partial objective coverage · period spanning multiple objective levels · trucks added/removed mid-period · overlapping downtime windows · objective with no allocations · unconfigured `rated_capacity_tons` · GPS loop with no rated capacity · division-by-zero on every ratio (→ null, never 0/NaN).

---

## 7. Reporting / UI — two sections, no KPI spam

**Section A — Objective Tracking** (the commitment): Planned · Confirmed (Actual) · Remaining · Achievement % · Data Quality % (+ Estimated shown distinctly, labelled informational).

**Section B — Capacity Context** (the why): Available Capacity · Allocated Capacity · Lost Capacity (by downtime category) · Utilization % · Allocation Efficiency % · Planning Adherence %.

Enterprise patterns: information hierarchy (A then B), progressive disclosure (per-truck table behind the fleet roll-up), contextual actions, audit history on allocations, status-driven objective lifecycle, responsive. No decorative charts; the one visual is the Planned→Allocated→Actual waterfall (carries real meaning).

---

## 8. Phased implementation (each phase = spec section + tests + 8-point review)

| Phase | Scope | Key risk |
|---|---|---|
| **1. Operations Calendar** | tables + `OperationsCalendarService` (operational-day counting) + admin UI | foundation for all pacing |
| **2. Per-truck capacity** | truck columns + `distribute` rewrite (capacity-proportional) + allocation ledger (propose/override/audit) | replaces even-split; migration fallback for NULL capacity |
| **3. Availability** | `truck_availability_windows` + `AvailabilityService` (union real sources) + Available/Lost capacity | no duplication of existing downtime |
| **4. Achievement split** | confirmed vs estimated throughout the engine + Data Quality % | remove all blending |
| **5. Allocation/utilization consumption** | AllocationService over `TruckAssignment` + the three-state KPIs | dispatch data completeness |
| **6. Two-section UI** | Section A + B, waterfall, per-truck disclosure, audit | consumes phases 1–5 |

Regression rule (carried from v1): legacy callers stay on the exact-match path; new KPIs are additive until the UI cuts over.

---

## 9. Navigation & module architecture (ratified)

Three top-level domains. Each menu = one distinct business capability. No name overlaps.

```
Fleet Planning              (Fleet Manager, Operations Manager, Executive)
├── Planning Dashboard      Objective · Capacity required/available · Allocation status · Achievement · Period selector
├── Rotation Planning       Capacity · Availability · Downtime impact · Resource constraints · Allocation engine · Feasibility/simulation
├── Objectives              Create/approve · Versions · Status · Target tonnage/rotations · Hierarchy (Year/Month/Week/Custom)
├── Objective History       Version compare · Revision timeline · Change reason · Approval history · Restore · Archived
└── Fleet Configuration     Per-truck capacity rules · Rotation rules · Productivity parameters · Operations calendar · Planning settings

Operations & Dispatch       (Dispatcher, Dispatch Supervisor, Operations Coordinator)
├── Daily Dispatch Board    (was "Programmation rotations" /logistics/planning)
├── Driver / Truck Assignments
├── Rotation Execution
├── WhatsApp Communications
└── Dispatch Monitoring

Administration
└── Audit Log               Immutable, system-wide: objective / planning / config / dispatch changes · security · user activity
```

**Boundary rules (ratified):**
- **Objectives define demand** (what we commit to). **Rotation Planning validates supply** (can we, with which trucks/constraints). **Dispatch executes.** **Reporting measures.** Never merged.
- A target can exist **without** an approved allocation; an allocation can be **recalculated** while the objective is preserved.
- Daily driver-dispatch (+ WhatsApp) is **Operations**, not Planning → extracted to its own domain.
- **Objective History = business feature** (revisions/restore for planners). **Audit Log = system feature** (immutable compliance trail). Both required; neither substitutes for the other.

**Route migration map**

| Current | Target |
|---|---|
| `Suivi planification` `/logistics/planning/weekly` | **Planning Dashboard** (rename, + capacity section) |
| `Planning flotte` `/logistics/fleet-roster` | **Rotation Planning** |
| `Objectifs` `/logistics/objectives` | **Objectives** (+ versioning/approval) |
| `Historique objectifs` `/fleet-roster/history` **+** `Journal des objectifs` `/objective-history` | **Objective History** (merged) |
| `Paramètres flotte` `/settings/fleet` | **Fleet Configuration** (expanded hub) |
| `Programmation rotations` `/logistics/planning` | **Operations & Dispatch › Daily Dispatch Board** (moved out) |
| `Journal d'activité` `/admin/audit-logs` | **Administration › Audit Log** |

> Physical nav flips per phase as target pages land (no dead links). Pure merges/relocations (two history pages → one; daily dispatch → Operations) can land early.

---

## 10. Governance, versioning & point-in-time parameters (ratified)

### 10.1 Objective lifecycle & versioning (business history)
- Objective status: `DRAFT → APPROVED` (+ `ARCHIVED`). A target may sit approved with no allocation yet.
- **`ObjectiveVersion`**: `objective_id`, `version_number`, `business_snapshot` (JSON of targets/allocations), `change_reason`, `created_by`, `created_at`. Powers Objective History (compare / timeline / restore). Never auto-deleted.

### 10.2 Versioned, point-in-time planning parameters
- **`PlanningParameter`**: `key`, `value`, `effective_from`, `effective_to`, `version`, `modified_by`, `change_reason`. **`ParameterVersion`** keeps the full change ledger.
- Parameter set (global Productivity Parameters): work hours/day, work days/week, buffer ratio, target utilization %, avg loading/unloading time, default cycle time, default dispatch window. Per-truck (Fleet Configuration): rated capacity, default rotations, availability factor, maintenance factor.
- **Hard rule — temporal correctness:** a plan/objective **snapshots the parameters active at its creation** and is **never recomputed** with newer values. Historical accuracy is mandatory. (Aligns with the existing "frozen objective snapshot" design.)
- All parameter changes: versioned · justified (`change_reason`) · logged to Audit Log · carry `effective_from`.
- **No magic numbers anywhere** — every calc references a documented, versioned parameter source (the `FleetCapacityService` constants become seeded parameters).

### 10.3 Permissions (ratified)
| Action | Permission | Role |
|---|---|---|
| View planning params | `planning-parameter-view` | Fleet Planner |
| Propose param changes | `planning-parameter-propose` | Fleet Manager |
| Approve/publish params | `planning-parameter-approve` | Planning Administrator |
| Approve objectives | `objective-approve` | Fleet Manager |
| Operations/dispatch | existing `daily-dispatch-*` | Dispatcher / Supervisor |

---

## 11. Revised phased plan (supersedes §8 sequence)

| Phase | Scope |
|---|---|
| **1. Operations Calendar** | calendars + days + `OperationsCalendarService` (operational-day counting) + Fleet Configuration entry |
| **2. Planning Parameters** | `PlanningParameter`/`ParameterVersion`, seed existing constants, propose→approve→publish governance, point-in-time resolver |
| **3. Per-truck capacity** | truck columns + capacity-proportional `distribute` + allocation ledger (propose/override/audit) |
| **4. Availability** | `truck_availability_windows` + `AvailabilityService` (union real sources) → Available/Lost capacity |
| **5. Achievement split** | confirmed vs estimated throughout + Data Quality % |
| **6. Allocation/utilization** | `AllocationService` over `TruckAssignment` → 3-state KPIs (Planned/Allocated/Actual) |
| **7. Objective versioning & approval** | status lifecycle + `ObjectiveVersion` + Objective History (compare/restore) |
| **8. Operations/Dispatch extraction** | move Daily Dispatch Board + WhatsApp into the Operations domain |
| **9. Planning Dashboard + Rotation Planning UI** | two-section dashboard; Rotation Planning (capacity/constraints/feasibility); nav cutover |

Each phase: migrations + documented formulas + tests + the 8-point review (business / architecture / data / API / security / scalability / UX / edge-case), then a pause for your review.
