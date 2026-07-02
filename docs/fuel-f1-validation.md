# Fuel F1 — Validation & Business Verification Report

> **UPDATE (2026-07-01): all blockers RESOLVED in the Stabilization phase — see
> [`fuel-f1-stabilization.md`](fuel-f1-stabilization.md). F1 is now stabilized (7/7 criteria green, 307 tests).**
> This document is retained as the record of what the gate originally found.
>
> **Original status: F1 NOT VALIDATED — STOP. Do not proceed to F2 (no Read Models / Calculators / KPIs / Dashboards).**
> The validation gate found **2 blocking inconsistencies** and several findings requiring business
> decisions. Method: the **real** parsers + the controller's exact persistence logic were run against
> **all** real Fleeti and EDK files inside a rolled-back DB transaction (dev DB untouched); imported
> totals were cross-checked against each source file's own `Résumé`/`Montant Total`.
> Date: 2026-07-01.

## Verdict

| # | Finding | Severity | Blocks F2? |
|---|---|---|---|
| **V1** | **EDK date parser rejects the "Jui" (June) abbreviation** → 70 of 74 rows of the current July recharge file, and 100% of account-family files, dropped as "Date invalide". **Data disappears.** | 🔴 **Blocking** | **YES** |
| **V2** | **Volume 2.0 and Carburant disagree on `consumed` (~+19%) and `refills` (~+105%) for the same period, and both currently write those columns** → the stored value depends on import order (not One Truth), contradicting the approved ownership. | 🔴 **Blocking** | **YES** |
| V3 | Account-family EDK files (`histo_rechaerge_compte*`, no card/plate) are non-importable by design — needs an explicit product decision (ignore vs model treasury). | 🟠 Decision | No (deferral) |
| V4 | 62/222 (28%) card recharges import with **no matched driver** (fuzzy `Porteur` match). | 🟡 Advisory | No |
| V5 | 1 card-file row unmatched truck (9th card / unknown plate); minor `Montant` rounding vs source. | 🟢 Minor | No |

Everything else **passed** (Fleeti idempotency, duplicate-file dedup, conservation vs source, EDK dedup, card↔truck 1:1). Details below.

---

## 1. Repository audit (validation scope)
Validated the F1 batch-import pipeline only (`FuelImportController` → `FleetiFuelParser` / `EdkFuelParser`
→ `FleetiDailyRecord` / `EdkFuelRecharge`). The live-telemetry pipeline (`FuelTracking`/`FuelEvent`) is out
of scope for import validation. DB entry state: `fleeti_daily_records`=0, `edk_fuel_recharges`=0 (clean slate).
8 active trucks (6066/6067/6071/6074/6077/6081/6082/6078 TTA1), 10 drivers.

## 2. Import audit (real files, real pipeline)

### Fleeti (all pass — 0 unmatched trucks, fully idempotent)
| File | Rows | Trucks | Invalid | consumed L | refills L | km | Re-import |
|---|---|---|---|---|---|---|---|
| Volume 2.0 (Jun) | 173 | 8 | 0 | 16 800.0 | 16 937.5 | 29 441.4 | +0 / all-updated |
| Carburant (Jun) | 173 | 8 | 0 | 20 043.5 | 34 722.3 | 29 582.2 | +0 / all-updated |
| Carburant (early Jun) | 46 | 8 | 0 | 6 016.4 | 6 244.1 | 10 386.3 | +0 / all-updated |
| Legacy Rapport (May-12) | 127 | 8 | 0 | 10 386.0 | 9 782.0 | 18 085.8 | +0 / all-updated |
| Legacy Rapport (May-14) | 503 | 8 | 0 | 46 235.3 | 48 552.6 | 80 806.3 | +0 / all-updated |
| Legacy Rapport (May-14 **duplicate file**) | 503 | 8 | 0 | 46 235.3 | 48 552.6 | 80 806.3 | **+0 inserted / 503 updated** ✅ |

- **Idempotency:** every re-import inserted **0** new rows (upsert on `(truck, record_date)`). ✅
- **Duplicate file:** the byte-identical `…20260514-1420 (1).xlsx` produced **0** new rows against the original — **duplicates cannot inflate the table**. ✅
- **Legacy compatibility:** all three retired "Rapport de carburant" files still parse. ✅

### EDK (parser defect exposed)
| File | Valid | Invalid | est. litres | FCFA | Re-import | Note |
|---|---|---|---|---|---|---|
| CARD May (219 rows) | 218 | 1 | 62 836 | 45 870 600 | +0 / 218 skipped ✅ | 1 unmatched truck (V5) |
| **CARD Jul (74 rows)** | **4** | **70** | 1 151 | 840 000 | +0 / 4 skipped | **70× "Date invalide" — V1 data loss** |
| ACCOUNT (11 rows) | 0 | 11 | 0 | 0 | — | 11× "Date invalide" (V1; also V3) |
| ACCOUNT (19 rows) | 0 | 19 | 0 | 0 | — | 19× "Date invalide" (V1; also V3) |

- **Idempotency:** re-importing the card files inserted **0** new rows (upsert on `UNIQUE (transaction_id, truck_id)`). ✅ Financial rows cannot be duplicated.
- **V1 exposed:** the July card file — the *current* export — loses **70 of 74** recharges.

## 3. Reconciliation audit (imported vs source ground truth)
Source truth read independently from each file's own `Résumé` / `Montant Total` (openpyxl, not the app parser):

| Check | Source (file) | Pipeline | Result |
|---|---|---|---|
| Carburant Jun `consumed` | 20 043.5 | 20 043.5 (DB sum, 173 rows) | ✅ exact |
| Carburant Jun `refills` | 34 722.3 | 34 722.3 | ✅ exact |
| Legacy May-14 `consumed` | 46 235.3 | 46 235.3 | ✅ exact |
| Volume 2.0 `refills` | 16 937.4 | 16 937.5 | ✅ (rounding) |
| Volume 2.0 `consumed` | 16 786.2 | 16 800.0 | ⚠️ +13.8 L (**+0.08%**) — Résumé "Consommé" vs sum-of-daily rounding; benign |
| Row conservation (Carburant Jun) | 173 truck-days | 173 DB rows | ✅ nothing lost/duplicated |
| EDK CARD May `Montant` | 45 870 610 | 45 870 600 (218 valid) | ✅ (−10 FCFA = the 1 unmatched row) |

**Fleeti conservation is exact** (≤0.1% rounding). **No Fleeti data disappears or duplicates.** EDK conservation
holds for the May file but **fails for the July file due to V1** (68–70 recharges lost).

## 4. Business validation

- **Card ↔ truck: clean 1:1.** Across both card files, each of the 8 trucks has exactly **one** card; **no card
  maps to >1 truck**, **no truck has >1 card**, **no reassignment**. (A "9th card" in the May file is the single
  unmatched row, V5.) → `FuelCard`/`FuelCardAssignment` can start as a simple 1:1 map.
- **Trucks changing cards:** none observed. ✅
- **Duplicate / repeated imports:** re-import inserts 0 rows for both EDK and Fleeti; byte-identical Fleeti file
  deduped. ✅
- **Transfers between cards:** the account-family files are `Transfert carte vers Compte` / `Rechargement par
  Espèces` movements — treasury, **not** truck-attributable (V3).
- **Recharge chronology:** plausible, Jan→Jul, ~24–31 recharges/truck; **but June/July coverage is understated
  because of V1** (July file mostly rejected). Spans that end in May (6066/6078/6082/6067) reflect the missing
  June "Jui" rows, not real inactivity.
- **Missing drivers:** 62/222 (28%) card recharges have no matched driver (fuzzy `Porteur`). Advisory only —
  driver is never a financial key.
- **Partial Fleeti / partial Carburant imports:** row-count grows by union of days (173 → 174), consumption is
  **not** doubled on merge, and tank/drain columns fill in correctly (2026-06-04: after Volume 2.0
  `volInit=0/drains=0`; after Carburant `volInit=84.9/drains=17.7`). The merge mechanism works — **but it also
  overwrote `consumed` 92.7 → 72.6 and `refills` 319.9 → 329.9, which is V2.**

## 5. Remaining inconsistencies (must be resolved before F2)

### V1 — EDK date parser drops June ("Jui") — 🔴 BLOCKING (data loss)
`EdkFuelParser::parseDate()` maps `'juin'→06` / `'juil'→07`, but the current exports abbreviate **June as
"Jui"** (3 chars) and July as "Juil" (4 chars). `substr('jui',0,4)='jui'` matches no key → `null` → "Date
invalide". Effect: the July card file loses 70/74 rows; account files show "Date invalide" (masking V3).
**Recommended fix (one line, unambiguous):** add `'jui' => '06'` to `FRENCH_MONTHS` (the 4-char `'juil'`
already resolves July first, so `'jui'` can only be June). Then re-validate: July card file should rise to ~72
valid (74 − 2 unmatched trucks), and account files should reclassify to "Camion non identifié" (V3).

### V2 — Volume 2.0 vs Carburant disagree on shared columns — 🔴 BLOCKING (One Truth)
For the **same June period**: `consumed` 16 800 (Volume 2.0) vs 20 043 (Carburant) = **+19.3%**; `refills`
16 937 vs 34 722 = **+105%**; `km` 29 441 vs 29 582 = +0.5% (close). The approved ownership
([`fuel-domain-architecture.md`](fuel-domain-architecture.md) §4) says **Volume 2.0 owns consumption/refuel;
Carburant owns tank + drains**. But the F1 parser emits `consumed`/`refills` for **both** layouts, and
`commitFleeti` treats them as shared → **whichever file is imported last wins**, so the stored consumption/refuel
depends on import order. This violates One Truth and must be resolved before any KPI/Calculator (F2) reads these
columns — otherwise every fuel-efficiency number becomes order-dependent.

## 6. Required business decisions
1. **(V2) Which source is authoritative for `consumed` and `refills`?** Recommended: **Volume 2.0 owns
   `consumed`, `consumed_per_100km`, `refills_*`, `km`; Carburant owns only `volume_initial/final`, `drains_*`**
   (Carburant's `consumed`/`refills` — sensor-derived, ~2× on refills — are then not written). Confirm, so the
   parser can be constrained to emit only owned columns per layout.
2. **(V2 corollary) If only Carburant is available for a period (no Volume 2.0), is 0 consumption acceptable**,
   or should Carburant be a fallback owner of consumption? Decides whether ownership is strict or fallback.
3. **(V3) Account-family EDK files** (`histo_rechaerge_compte*`, no card/plate): ignore them (recommended — they
   are master-account treasury, not truck fuel), or model a separate `FuelCardAccountMovement`?
4. **(V4) Driver attribution:** is a 28% unmatched-driver rate acceptable (driver stays advisory), or is a
   `FuelCard → driver` assignment needed to attribute recharges reliably?
5. **(General) Fuel price basis / currency** (still open from the ADR): `Montant` is FCFA but the price default
   is MRU-context 730 — confirm the currency and the per-period price used for estimated litres.

## 7. Technical debt (carried, non-blocking)
- Import commit/merge logic lives in `FuelImportController` (extract to `FuelImportService` — frozen rule:
  controllers orchestrate only).
- Synchronous parse with raised `memory_limit`/`set_time_limit` (queue it — Track B).
- Native `confirm()/alert()` in `FuelImportDrawer` (`docs/audit/02`).
- Per-period price history absent (estimated litres use one current price).

## 8. Readiness for F2
**NOT READY.** F1 is structurally sound (idempotent, deduped, legacy-compatible, Fleeti-conserving) but **not yet
business-valid**: V1 loses current EDK data, and V2 makes stored consumption/refuel import-order-dependent.
**Proceed to F2 only after** V1 is fixed, V2 is decided + the parser constrained to owned columns, V3–V5 are
ruled on, and this validation is re-run green. No Fuel Read Models, Calculators, KPIs, or Dashboards until then.
