# Fuel Import Validation Layer — Implementation & Report

> **Status: implemented + verified. 315 tests pass (8 new), 0 type errors.** Every EDK row is now
> classified before it can reach the canonical `edk_fuel_recharges` table; no row is silently discarded.
> This precedes (does not start) F2 — no Read Models / KPIs / Calculators / Dashboards were built.
> Date: 2026-07-01.

## What it does
The EDK import path is now **parse → classify → persist**:
1. `EdkFuelParser` — pure CSV syntax (one row per data line, original text preserved). No truck/price logic.
2. `EdkImportValidator` — classifies **every** row into one status; resolves detected truck/card/driver.
3. `FuelImportController::commitEdk` — writes VALID rows to the canonical table (tagged with their import
   batch) and **quarantines every exception** in `edk_import_exceptions` with full context.

## Statuses & precedence (first match wins)
| # | Status | Rule |
|---|---|---|
| 1 | `MANUAL_REVIEW` | date unparseable **or** amount ≤ 0 |
| 2 | `UNKNOWN_TRUCK` | no matricule in the `Porteur` resolves to any truck |
| 3 | `INACTIVE_TRUCK` | matched truck exists but `is_active = false` (no longer owned by AMC) |
| 4 | `DUPLICATE` | `transaction_id` already in canonical **or** seen earlier in this batch |
| 5 | `CARD_MISMATCH` | card's established owner (history / batch) is a **different** truck |
| 6 | `DRIVER_MISMATCH` | a driver detected in `Porteur` is **not** assigned (`TruckDriverAssignment`) to the truck |
| 7 | `VALID` | none of the above |

Only `VALID` reaches `edk_fuel_recharges`. All others are stored for review (never auto-imported, never dropped).

## Storage (nothing is lost)
- **`fuel_import_batches`** — one row per import run: source, filename, total/valid/exception counts,
  `category_counts` (JSON), user, timestamp. Valid recharges link back via `edk_fuel_recharges.fuel_import_batch_id`.
- **`edk_import_exceptions`** — one row per rejected transaction with: **original CSV** (`raw_line`, `line_number`),
  `status` + `reason`, `transaction_id`, `card_number`, `holder_raw`, `amount_fcfa`, `estimated_litres`,
  `occurred_at`, **detected** `truck_id` / `driver_id`, batch, timestamps.

## Import Validation Report (real EDK files, price 730)
Run through the real classify-and-commit flow (rolled back; dev DB left clean):

| File | Total | Valid | Exceptions | By category | Integrity |
|---|---|---|---|---|---|
| CARD May (219) | 219 | 218 | 1 | `UNKNOWN_TRUCK ×1` | canonical 218 + quarantine 1 = **219 (nothing lost)** |
| CARD Jul (74) | 74 | 72 | 2 | `UNKNOWN_TRUCK ×2` | 72 + 2 = **74** |
| ACCOUNT (11) | 11 | 0 | 11 | `UNKNOWN_TRUCK ×11` | 0 + 11 = **11** |
| ACCOUNT (19) | 19 | 0 | 19 | `UNKNOWN_TRUCK ×19` | 0 + 19 = **19** |

- **Nothing discarded:** for every file, `canonical + quarantined == total`.
- **Exceptions persisted with context** — e.g. `status=UNKNOWN_TRUCK reason="Camion inconnu — matricule
  introuvable dans le porteur" line=18 montant=210000 raw="0;565873234319772;23-Jui-2026 14:18:20;210000;…"`.
- **Idempotent:** re-importing a file classifies all prior valids as `DUPLICATE` (0 new canonical rows).
- **Account-family files** (no card/plate) correctly land as `UNKNOWN_TRUCK` exceptions — they are treasury
  movements, not truck fuel, and are now visibly quarantined rather than silently dropped.

> Note: with the current data card↔truck is a clean 1:1, so no `CARD_MISMATCH`/`DRIVER_MISMATCH`/`INACTIVE_TRUCK`
> fired on the real files — but each rule is exercised by a dedicated unit test (below). These categories will
> surface real anomalies as card reassignments, retired trucks, or driver swaps occur.

## Verification
- **Unit tests (`EdkImportValidatorTest`)** — one case per status: VALID, MANUAL_REVIEW (bad date + bad amount),
  UNKNOWN_TRUCK, INACTIVE_TRUCK, DUPLICATE (vs canonical), CARD_MISMATCH (card on another truck),
  DRIVER_MISMATCH (unassigned driver), plus "every row receives a status / counts reconcile".
- **Parser contract test** updated (`FuelStabilizationTest`) — pure parser still handles the "Jui" June date.
- **Full suite:** 315 passed (3 862 assertions); `tsc --noEmit` 0 errors.
- **UI:** the import drawer now shows the validation report — total / valid / exceptions + per-category chips +
  a quarantined-exceptions table; commit reads "Importer N valides (M en quarantaine)".

## Files
**New:** `app/Enums/EdkImportStatus.php` · `app/Models/FuelImportBatch.php` · `app/Models/EdkImportException.php` ·
`app/Services/Fuel/EdkImportValidator.php` · migration `…_create_fuel_import_validation_tables.php` ·
`tests/Feature/Fuel/EdkImportValidatorTest.php`.
**Modified:** `app/Services/Fuel/EdkFuelParser.php` (→ pure parse) · `app/Http/Controllers/FuelImportController.php`
(classify + batch + quarantine; removed 2 dead imports) · `resources/js/pages/fuel/components/FuelImportDrawer.tsx`.

## Remaining / follow-ups (not blocking this layer)
- An **exceptions review surface** (list/resolve/promote a quarantined row) is not built — exceptions are stored
  and reportable now; a management screen is a candidate follow-up.
- `CARD_MISMATCH` currently derives a card's owner from recharge history (no `FuelCard` registry yet) — will
  sharpen once the registry (earlier F-roadmap) lands.
- V3–V5 business decisions (account-family handling, driver attribution, price/currency) still open.

## Readiness
The validation layer is complete and verified: **every EDK transaction is classified and every exception stored
with full context.** F2 (Fuel Read Models) remains gated on your approval.
