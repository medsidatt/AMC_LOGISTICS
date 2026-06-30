# Operational Intelligence Platform — Official Architecture (FROZEN)

> **Status: FROZEN (2026-06-29).** This is the official architecture of the AMC Operational
> Intelligence Platform. Future work conforms to it; it is not redesigned. Governed by the
> [Platform Design Constitution](workspace-standard.md) (One Truth · Decision-first · Exceptions-first ·
> Action-before-statistics · Zero hardcoded operational values · Zero business logic in UI).
> Implementation (R1.1) begins only after explicit approval.

## 1. Layered architecture (no layer bypasses another)
```
Database
   ▼
Read Models                 (normalize operational data; the only DB readers)
   ▼
Operational Parameters  ──► OperationalParameterService   (configurable VALUES only; cached/typed/audited)
   ▼
Domain Calculators          (business RULES; consume Read Models + Parameters; one responsibility each)
   ▼
Business Events             (immutable value objects = operational FACTS; no DB, no mutation)
   ▼
KPI Registry                (each KPI defined ONCE)
   ▼
Operational Intelligence    (Events + KPI Registry → business CONCLUSIONS; no DB/config/formulas)
   ▼
Dashboard Translators       (Intelligence → cards/queues/timelines/widgets; no calc/rules/DB)
   ▼
Command Centers             (render only)
```

## 2. Folder structure (business domain, not technical)
```
app/Domain/Operations/
├── Parameters/        OperationalParameter usage helpers (values live in DB + service)
├── Contracts/         one interface per calculator (+ ReadModel / Event contracts)
├── ReadModels/        TransportTracking/Fleet/Fuel/Maintenance/Inspection/Dispatch read models
├── Calculations/      Capacity/Weight/Rotation/Dispatch/Productivity/Fuel/Cycle/Maintenance/Inspection/Billing/Objective/Utilization
├── Events/            immutable business-event value objects + derivers
├── KPI/               KpiRegistry + KPI definitions
├── Intelligence/      OperationalIntelligenceEngine + conclusion value objects
├── Translators/       Dashboard Translators (presentation prep)
└── OperationsDomain.php   single entry point (facade)
```
**Entry point (facade), business language:**
`OperationsDomain::weight()->gap(...)` · `::capacity()->available(...)` · `::maintenance()->level(...)` · `::billing()->readiness(...)`.

## 3. Layers — responsibilities · dependencies · files · risks · verification · rollback

### L0 · Read Models (`ReadModels/`)
- **Responsibility:** normalize operational data; **the only components that build Eloquent queries.** Calculators never query directly.
- **Dependencies:** Eloquent models (TransportTracking, Truck, FleetSetting, Fuel*, Maintenance, InspectionChecklist, DailyDispatch).
- **Files (new):** `TransportTrackingReadModel`, `FleetReadModel`, `FuelReadModel`, `MaintenanceReadModel`, `InspectionReadModel`, `DispatchReadModel` (+ `Contracts/*ReadModelInterface`).
- **Risks:** a read model must return the same rows the inline queries returned → characterization parity tests.
- **Verification:** snapshot the result sets of the current inline queries; assert read models reproduce them.
- **Rollback:** additive; callers switch to read models per increment, revertable per commit.

### L1 · Operational Parameters (`Parameters/` + service)
- **Responsibility:** store/serve **configurable values only** (thresholds, capacities, SLA days, fiscal-start-day, maintenance limits, warning %, target rotations, cycle/buffer/work hours). No logic.
- **Dependencies:** none.
- **Files (new):** migration `…_create_operational_parameters_table`, `app/Models/OperationalParameter.php`, `app/Services/OperationalParameterService.php` (cached, typed `float/int/bool/string/enum`, **no business defaults** — missing key throws; audited via `updated_by`), `OperationalParameterSeeder` (validated; seeds each key to its **current** value), and enums `app/Enums/{OperationalParameterKey,ParameterCategory,ParameterUnit,ParameterOwner}`. **Defaults live in the seeder/ADRs, never in the service (ADR-008).**
- **Risks:** cache staleness → invalidate on save; seed must equal current literals.
- **Verification:** unit tests assert each seeded key == its current hardcoded value (no behaviour change).
- **Rollback:** additive — drop migration.

**Migration — `FleetSetting` → `OperationalParameter` (ADR-009):** `FleetSetting` is now **temporary compatibility storage**; `OperationalParameter` is the **future single source of truth**. The Fleet Settings UI saves through `FleetSettingsController`, which **dual-writes** the same validated values into both stores (`FleetSettingParameterMap`), and `operations:sync-parameters` (idempotent, `--dry-run`) back-fills the parameters from live `FleetSetting`. **Domain Calculators read parameters only — never `FleetSetting`.** R1.8 removes the remaining `FleetSetting` reads (the KPI services + `TransportTracking::weightGapThreshold()`) once each consumer is migrated under characterization tests.

### L2 · Domain Calculators (`Calculations/` + `Contracts/`)
- **Responsibility:** **own the business rules.** One responsibility each, behind an **interface** (Contracts) and the `OperationsDomain` facade. Logic lives here; values come from Parameters; data comes from Read Models.
- **Calculators ↔ interfaces:** Capacity/Weight/Rotation/**Dispatch**/Productivity/Fuel/Cycle/Maintenance/Inspection/Billing/Objective/Utilization, each with `…CalculatorInterface`. **Dispatch** is a distinct business capability (dispatch readiness · planned-vs-started · assignment completeness · unassigned trucks · dispatch delay · execution) and does not reuse Rotation/Capacity calculators — added inside this layer, no layer/boundary/rule change.
- **Absorbs (move, not rewrite):** weight-gap-violations ×4, default-capacity ×6, cycle-days ×2, discipline ×2, fuel-yield ×4, load-rate ×3, tonnage/rotation aggregations ×4, fiscal-month 22→21 ×5, maintenance-level ×3, availability/saturation.
- **Dependencies:** Read Models + OperationalParameterService.
- **Files (new):** `Domain/Operations/Calculations/*`, `Contracts/*CalculatorInterface`, `OperationsDomain.php`. **Refactor:** Driver/Truck/Fleet KPI services, RotationAchievement, FleetCapacity, DashboardDataService, TrackingDashboardController, HseController delegate to calculators.
- **Risks:** numeric divergence on move.
- **Verification:** **characterization tests** (snapshot current outputs → assert identical after move).
- **Rollback:** per-commit; old methods retained until R1.8 proves equivalence.

**Parameters vs Rules:** Parameters = thresholds/capacities/SLAs/fiscal/limits/%. Calculators = discipline-score, billing-readiness, utilization/availability, maintenance-level, objective-confidence, fuel-efficiency, anomaly classification. *Parameters configure; calculators decide.*

### L3 · Business Events (`Events/`)
- **Responsibility:** immutable **value objects expressing operational facts** — `MissingTransportTicket`, `TruckUnavailable`, `MaintenanceOverdue`, `WeightAnomaly`, `BillingBlocked`, `ObjectiveBehindSchedule`, `InspectionExpired`, `FleetCapacityReduced`. **They never mutate and never query the database.** Each holds `type · severity · owner · subject · business_impact · required_action · deadline · drill_down · status`.
- **Design:** **derived on demand** (no persistence in R1 — honors "only the operational_parameters table"). Derivers compute events from Calculators (reuse the existing `DailyDispatchEventDeriver` pattern).
- **Dependencies:** Domain Calculators (for facts). Derivers may use Read Models; **the event objects themselves do not.**
- **Files (new):** `Domain/Operations/Events/*` (event VOs + derivers + `Contracts/BusinessEventInterface`). **No consumer in R1.**
- **Risks:** none to existing behaviour (additive, unconsumed).
- **Verification:** unit tests on each deriver (fact correctness); immutability tests on the VOs.
- **Rollback:** additive — delete the namespace.

### L4 · KPI Registry (`KPI/`)
- **Responsibility:** **one definition per KPI** — `name · business_question · owner · formula(→ calculator) · data_source · refresh · thresholds(→ params) · business_impact · drill_down · recommended_action`.
- **Dependencies:** Calculators + Parameters.
- **Files (new):** `Domain/Operations/KPI/KpiRegistry.php` + definitions.
- **Risks:** none (no consumer change).
- **Verification:** structural tests (resolvable, no duplicate keys).
- **Rollback:** additive.

### L5 · Operational Intelligence (`Intelligence/`)
- **Responsibility:** transform **Business Events + KPI Registry** into business **conclusions** (`title · severity · owner · business_impact · required_action · drill_down`). **Must never** query Eloquent, call repositories, read configuration, or calculate formulas.
- **Dependencies:** Business Events + KPI Registry **only**.
- **Files (new):** `Domain/Operations/Intelligence/*` (engine + conclusion VOs).
- **Risks:** none in R1 (contract + plumbing; no UI, no shipped conclusions).
- **Verification:** unit tests on conclusion shape + the "no DB/config" boundary.
- **Rollback:** additive.

### L6 · Dashboard Translators (`Translators/`)
- **Responsibility:** convert Intelligence into presentation — prepare cards/queues/timelines/widgets. **Forbidden:** calculations, business rules, thresholds, database queries.
- **Dependencies:** Operational Intelligence only.
- **R1 scope:** define the contract; **dashboards are not touched** (rebuilt in R2).

## 4. Architecture Rules (permanent · mandatory)
- Controllers never calculate · never read Operational Parameters · orchestrate only.
- Dashboards / UI never calculate · never read Operational Parameters · never compute KPIs.
- Events never access the database. Intelligence never accesses the database.
- Calculators own business rules. Parameters own configuration. Read Models own data normalization. KPI Registry owns KPI definitions.
- Every KPI has one definition. Every calculation has one owner. Every parameter has one owner. Every event has one owner.
- Every dashboard consumes Operational Intelligence only.
- No layer bypasses another (Database → Read Models → Parameters → Calculators → Events → KPI Registry → Intelligence → Translators → Command Centers).

## 5. Implementation roadmap (each: independently committable · behaviour-preserving · tests green)
- **R1.1** Operational Parameters (migration · model · service · cache · seeder) — no behaviour change.
- **R1.2** Read Models — normalize data; parity tests vs current inline queries; no calculator change yet.
- **R1.3** Domain Calculators + Contracts + `OperationsDomain` facade — move the **first** dups (weight-gap, capacity) with characterization tests.
- **R1.4** Business Events — immutable VOs + derivers; **unconsumed**.
- **R1.5** KPI Registry — single definitions.
- **R1.6** Operational Intelligence — conclusions contract + plumbing; **no UI**.
- **R1.7** Dashboard Translators — contract only; **no dashboard change**.
- **R1.8** Incremental migration — move remaining dups; **delete old implementations only after characterization tests prove identical behaviour**.

## 6. Constraints (R1)
No code until approved · no dashboard/UI work · no new KPIs · no business-rule/value change (params seeded to current values; the **capacity 25/45** and **three weight-gap thresholds 0.5t/300kg/150kg** stay flagged + distinct, decided by the business) · **only** the `operational_parameters` table is added (Business Events derived, not persisted) · single responsibility per class · reuse over abstraction.
