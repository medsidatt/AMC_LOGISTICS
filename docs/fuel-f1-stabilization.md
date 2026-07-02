# Fuel Import Stabilization — Report

> **Outcome: all 3 blockers resolved; 7/7 acceptance criteria proven green; 307 tests pass; 0 type errors.
> F1 is stabilized and recommended for approval.** Method: real parsers + the controller's exact persistence
> logic run against **all** real Fleeti/EDK files in a rolled-back transaction; the dev DB was left clean.
> Date: 2026-07-01. Companion to [`fuel-f1-validation.md`](fuel-f1-validation.md).

## Root cause analysis & fixes

### Blocker 1 — EDK rows rejected for "valid" dates (data loss)
- **Root cause.** `EdkFuelParser::FRENCH_MONTHS` maps `'juin'→06` / `'juil'→07` (4-char). The current exports
  abbreviate **June to 3-char "Jui"** (July stays 4-char "Juil"). `parseDate()` computes `substr($key,0,4)='jui'`
  and `substr($key,0,3)='jui'` — neither is a map key → `null` → "Date invalide".
- **Why it happens.** Only June is truncated in EDK's date format; every June-dated recharge failed.
- **Affected code.** `app/Services/Fuel/EdkFuelParser.php` (`FRENCH_MONTHS`, `parseDate()`).
- **Smallest correct fix.** Added `'jui' => '06'`. The 4-char `'juil'` still resolves July first, so `'jui'`
  can only mean June — unambiguous.
- **Before → after (real files).** July card file **4 → 72** valid (68 recharges recovered); account files
  reclassify from "Date invalide" to the correct "Camion non identifié" (dates now parse).

### Blocker 2 — "one imported month contains 179 days" (impossible)
- **Root cause.** **No parsing or dedup defect exists.** Two real contributors were conflated:
  1. **Miscount:** a month's `COUNT(*)` is *truck-day records* (8 trucks × ~22 days = 169–185), not calendar
     days. E.g. Feb = 185 truck-days across 28 calendar dates.
  2. **Committed test pollution:** an earlier validation harness left **173 June rows + 4 EDK rows committed**
     (a transaction that did not roll back), which accumulated on manual re-runs and inflated month counts.
- **Structural guarantee.** `fleeti_daily_records` has `UNIQUE(truck_id, record_date)` → **at most one row per
  truck per calendar day** → distinct calendar dates per month ≤ days-in-month, always. ">31 calendar days in a
  month" is structurally impossible.
- **Affected code.** None (no defect). **Remediation:** truncated the leaked rows (dev DB back to 0) and added a
  standing assertion in the validation harness.
- **Before → after.** Before: polluted DB, month counts inflated/misread. After: proven across **all** files —
  every month's distinct calendar dates ≤ its length; **max records for any truck-month = 28**; records ==
  distinct dates (no duplicates); **0 violations**.

### Blocker 3 — Volume 2.0 & Carburant overlap → import order changes data
- **Root cause.** `FleetiFuelParser` emitted `consumed`/`refills` for **both** layouts and `commitFleeti` treated
  them as shared columns → last import wins. The two exports disagree materially (consumed +19%, refills +105%),
  so stored consumption depended on import order.
- **Affected code.** `app/Services/Fuel/FleetiFuelParser.php` (`parseDetailSheet`) +
  `app/Http/Controllers/FuelImportController.php` (`commitFleeti`).
- **Smallest correct fix — format-driven ownership.**
  - `detectFormat()` classifies the workbook: `volume2` (Volume de carburant 2.0) · `carburant` · `rapport` (legacy).
  - `ownedFields()` returns each format's **disjoint** authoritative columns: Volume 2.0 → `kilometers, consumed,
    consumed_per_100km, refills_count, refills_volume`; Carburant → `volume_initial, volume_final, drains_count,
    drains_volume`; legacy Rapport → all.
  - Each parsed row carries `_owned`; `commitFleeti` persists **only** owned columns (all values still travel for
    preview/totals). Volume 2.0 and Carburant now write disjoint sets → merge is order-independent.
- **Before → after.** Before: `consumed` = 16 800 or 20 043 depending on order. After: **always 16 800** (Volume
  2.0) with tank from Carburant, **byte-identical in both import orders**.

## Code changes
| File | Change |
|---|---|
| `app/Services/Fuel/EdkFuelParser.php` | `+ 'jui' => '06'` (Blocker 1) |
| `app/Services/Fuel/FleetiFuelParser.php` | `detectFormat()`, `ownedFields()`, per-row `_owned`, format threaded through `parseDetailSheet()` (Blocker 3) |
| `app/Http/Controllers/FuelImportController.php` | `commitFleeti` persists the parser-declared `_owned` set (Blocker 3) |
| `tests/Feature/Fuel/FuelStabilizationTest.php` | **new** — EDK "Jui" parse guard + import-order-independence guard |

## Validation — before vs after (all 7 required criteria)
| Criterion | Before | After |
|---|---|---|
| Every EDK row imports unless genuinely invalid | July file 4/74 (V1) | **72/74** (2 unmatched trucks = genuinely invalid) |
| Every imported date parsed correctly | June "Jui" rejected | **June + July parse**; account dates parse (fail later on no-truck) |
| Every truck has the correct number of daily records | (masked by pollution) | records == distinct dates; **max truck-month = 28**; 0 dup |
| No month exceeds possible calendar days | reported 179 | every month ≤ its length; **0 violations** (Jan 31/31 … Feb 28/28) |
| Duplicate imports idempotent | ✅ | ✅ 503 → 503 → 503 (byte-identical dup file adds 0) |
| Deterministic ownership of every field | order-dependent | **disjoint owners**; consumed=Volume2, tank=Carburant |
| Import order no longer changes business data | 16 800 vs 20 043 | **identical in both orders** |

Regression suite: **307 tests pass** (3 851 assertions), **`tsc --noEmit` 0 errors**. Fleeti conservation vs
source `Résumé` remains exact (≤0.1%). Dev DB left clean (`fleeti_daily_records`=0, `edk_fuel_recharges`=0).

## Remaining issues (non-blocking — business decisions, not defects)
- **V3** Account-family EDK files (`histo_rechaerge_compte*`, no card/plate) are fully rejected ("Camion non
  identifié") — correct (treasury movements, not truck fuel). Needs an explicit *ignore vs model-separately* ruling.
- **V4** ~28% of card recharges import with no matched driver (advisory `Porteur` match only).
- **V5** Currency/price basis (FCFA amount vs MRU-context 730 price) still to confirm.
- **Tech debt (carried):** extract commit/merge into a `FuelImportService`; queue the import; replace native
  `confirm()/alert()`; per-period price history.

## Is F1 finally approved?
**Yes — F1 is stabilized.** All three blockers are resolved (1 & 3 by code fixes with regression guards; 2 shown
to be a non-defect guaranteed by the `UNIQUE(truck_id, record_date)` constraint, with the leaked test data
cleaned). Every acceptance criterion is proven green on real multi-month data, with no regressions. V3–V5 are
business decisions that do not block the import foundation and can be settled in parallel.

**Recommendation:** approve F1 and authorize F2 (FuelReadModel first). No Read Models / Calculators / KPIs /
Analytics / Dashboards were built in this phase.
