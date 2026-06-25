# Backlog — KPI exact-period objective logic

**Status:** Open · **Priority:** Low · **Source:** Manual planning / hierarchy validation work (approved)

## Context
Réalisation resolves a target read-only (`RotationAchievementService::forPeriod`, view-mode path), manual planning always winning, in this order:

1. **exact** — manual objective for the period (`ObjectiveTargetResolver::exactForMode`).
2. **estimated — hierarchical-mean child reference** (`hierarchicalChildReference()`): when manual CHILD objectives exist inside the period (year→months, month→weeks), the reference is `Σ(manual children) + missing child slots × mean(manual children)` — every slot contributes, planned slots at their figure and unplanned slots at the mean of the planned ones (e.g. year Jan/Feb/Mar = 2200/2100/1900 → 6200 + 9 × 2066.67 = **24 800**, not a flat ≈2067).
3. **estimated — parent-remaining reference** (`referenceTarget()`): only when there are NO manual children, the unallocated parent budget is split across sibling periods by operational-days × capacity. Weeks (lowest level) fall back here to the parent monthly allocation.
4. **none**.

All estimation is a read-only reporting benchmark: never persisted, distributed, redistributed, or surfaced in Planning. It lives solely in Réalisation.

**KPI rollups were deliberately left unchanged.** `FleetKpiService` and `DriverKpiService` still call `ObjectiveTargetResolver::resolve(…, CUSTOM)`, which **aggregates/prorates** overlapping objectives by date overlap (a read-time derivation). This is acceptable for KPI rollups but is *not* exact-period.

## Question to decide later
Should KPI planned-tonnage also use exact-period objectives (no aggregation/proration), to match Réalisation's manual-planning semantics?

- Pro: consistent "only manually-created objectives, no derivation" everywhere.
- Con: KPIs are fleet rollups over arbitrary date ranges; exact-period matching may leave them with no target for custom ranges.

## If scheduled
- Add an exact/period-aware path for `FleetKpiService:63` and `DriverKpiService:44` (or a resolver flag), without touching `ObjectiveTargetResolver::resolve()` used elsewhere.
- No change to `forPeriod` (already exact) or the objective model.

## Do NOT
- Touch `ObjectiveTargetResolver::resolve()` chain globally, the objective model, distribution, or realisation logic.
