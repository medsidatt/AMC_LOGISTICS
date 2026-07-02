# Fuel Import Validation — Redesign (three independent decisions)

> **Status: SUPERSEDED by [`fuel-import-validation-redesign-v2.md`](fuel-import-validation-redesign-v2.md)**
> (which adds the independent `TransactionType` axis). Retained for history. No code was produced from either doc.
> Date: 2026-07-01.

## 0. Executive summary
The current importer collapses three different business questions into one `EdkImportStatus` enum, and only
`VALID` reaches the canonical table — so a legitimate **financial** transaction (e.g. the company card used on a
non-fleet truck) is quarantined *out of* canonical history merely because it is not KPI-eligible. That is wrong
for accounting, audit, and fraud investigation.

The redesign separates **three orthogonal decisions** and lets a row carry **multiple reasons**:

| Decision | Values | Question |
|---|---|---|
| **ImportDecision** | `ACCEPT` · `REJECT` | Can this record enter the canonical financial ledger? |
| **KpiEligibility** | `ELIGIBLE` · `NOT_ELIGIBLE` | May it influence operational Fuel KPIs? |
| **ReviewDecision** | `NONE` · `REQUIRED` | Does a human need to look at it? |
| **Reasons[]** | set of codes | *Why* — the evidence behind the three decisions |

Result: almost everything is **imported** (financial truth is preserved); **KPI eligibility** is a separate flag
so analytics see only clean operational fuel; **review** is a third flag driving a queue. Only *objectively
corrupt* rows (duplicate, malformed, impossible date/amount) are rejected.

---

## 1. Audit of the current implementation
Built in the previous phase:
- **`EdkImportStatus`** (single enum): `VALID · UNKNOWN_TRUCK · INACTIVE_TRUCK · CARD_MISMATCH · DRIVER_MISMATCH ·
  DUPLICATE · MANUAL_REVIEW`.
- **`EdkImportValidator`** — first-match precedence assigns exactly one status per row.
- **`FuelImportController::commitEdk`** — **only `VALID` → `edk_fuel_recharges`**; every other status → quarantined
  in **`edk_import_exceptions`** (outside canonical).
- **`edk_fuel_recharges`** — `truck_id` is **NOT NULL** (FK-constrained); one status implied (all rows are "valid").
- **`fuel_import_batches`** — batch audit with `category_counts` keyed by the single status.

Real-file behaviour: account-family rows and non-fleet plates are labelled `UNKNOWN_TRUCK` and **kept out of
canonical**. Card↔truck is 1:1 today, so mismatches don't yet fire, but the structural flaw is already live.

## 2. Why the single-status model is insufficient
1. **It conflates three concerns.** "Can I store it?", "Does it count for KPIs?", and "Should a human review it?"
   are independent. One enum can only express one axis, so it silently picks *reject-and-hide* for every anomaly.
2. **It destroys financial history.** The worked example — transaction `565873234319772`, plate **`AA 463 AQ`**
   (a truck no longer in the fleet; a driver mistakenly used the company card and returned it) — is a **real
   financial movement** and a **fraud/audit signal**. Under the current model it becomes `UNKNOWN_TRUCK` and is
   quarantined out of canonical, so accounting and investigators lose it from the ledger.
3. **It mislabels account transfers.** `histo_rechaerge_compte*` movements (`Transfert carte vers Compte`,
   `Rechargement par Espèces`) are financial treasury events, not truck fuel — yet they're bucketed as
   `UNKNOWN_TRUCK`, which is factually wrong and pollutes the "unknown truck" exception list.
4. **A row can be several things at once.** Inactive truck **and** driver mismatch, or account transfer **and**
   duplicate. A single status cannot represent co-occurring reasons; the first-match precedence hides the rest.
5. **KPI-eligibility is entangled with persistence.** "Not KPI-eligible" was implemented as "not imported", which
   is the core category error this redesign fixes.

## 3. The new validation model

Each row is classified into a **decision triple + reason set**. The three decisions are **derived from the
reasons** by fixed rules (below), but stored explicitly for fast, unambiguous querying.

### Reason → per-reason contribution
| Reason | Import | KPI | Review | Meaning |
|---|---|---|---|---|
| *(none / NORMAL)* | ACCEPT | ELIGIBLE | NONE | clean operational recharge on an active fleet truck |
| `UNKNOWN_TRUCK` | ACCEPT | NOT_ELIGIBLE | REQUIRED | plate not in fleet (e.g. `AA 463 AQ`) — financial + fraud signal |
| `INACTIVE_TRUCK` | ACCEPT | NOT_ELIGIBLE | REQUIRED | truck matched but retired from the fleet |
| `DRIVER_MISMATCH` | ACCEPT | NOT_ELIGIBLE | REQUIRED | detected driver not assigned to the truck |
| `CARD_MISMATCH` | ACCEPT | NOT_ELIGIBLE | REQUIRED | card historically belongs to a different truck |
| `ACCOUNT_TRANSFER` | ACCEPT | NOT_ELIGIBLE | NONE | treasury movement (account-family), not truck fuel |
| `DUPLICATE` | REJECT | NOT_ELIGIBLE | NONE | transaction already in the ledger — original is the record |
| `INVALID_DATE` | REJECT | NOT_ELIGIBLE | REQUIRED | unparseable/impossible date — corrupt |
| `INVALID_AMOUNT` | REJECT | NOT_ELIGIBLE | REQUIRED | non-positive/impossible amount — corrupt |
| `MALFORMED_ROW` | REJECT | NOT_ELIGIBLE | REQUIRED | wrong column count / unreadable line — corrupt |

### Aggregation rules (a row may carry several reasons)
- **ImportDecision** = `REJECT` if **any** reason is a reject-reason (`DUPLICATE`, `INVALID_DATE`,
  `INVALID_AMOUNT`, `MALFORMED_ROW`); else `ACCEPT`. *Corrupt data is never trusted into the ledger.*
- **KpiEligibility** = `ELIGIBLE` **iff the reason set is empty** (pure normal recharge). Any reason ⇒
  `NOT_ELIGIBLE`. *(Also requires a resolved, active truck — which "no reasons" already implies.)*
- **ReviewDecision** = `REQUIRED` if any reason's review column is REQUIRED; else `NONE`. (`ACCOUNT_TRANSFER` and
  `DUPLICATE` are known/benign → `NONE`.)

### Canonical business cases (matches the brief)
| Case | Import | KPI | Review | Reasons |
|---|---|---|---|---|
| Normal recharge | ACCEPT | ELIGIBLE | NONE | — |
| Inactive truck | ACCEPT | NOT_ELIGIBLE | REQUIRED | `INACTIVE_TRUCK` |
| Driver mismatch | ACCEPT | NOT_ELIGIBLE | REQUIRED | `DRIVER_MISMATCH` |
| Card mismatch | ACCEPT | NOT_ELIGIBLE | REQUIRED | `CARD_MISMATCH` |
| Account recharge | ACCEPT | NOT_ELIGIBLE | NONE | `ACCOUNT_TRANSFER` |
| Non-fleet truck (`AA 463 AQ`) | ACCEPT | NOT_ELIGIBLE | REQUIRED | `UNKNOWN_TRUCK` |
| Duplicate | REJECT | NOT_ELIGIBLE | NONE | `DUPLICATE` |
| Malformed CSV | REJECT | NOT_ELIGIBLE | REQUIRED | `MALFORMED_ROW` |

## 4. Decision objects (to define in code — not implemented here)
- `enum FuelImportDecision: string { ACCEPT; REJECT; }`
- `enum FuelKpiEligibility: string { ELIGIBLE; NOT_ELIGIBLE; }`
- `enum FuelReviewDecision: string { NONE; REQUIRED; }`
- `enum FuelImportReason: string { UNKNOWN_TRUCK; INACTIVE_TRUCK; DRIVER_MISMATCH; CARD_MISMATCH;
  ACCOUNT_TRANSFER; DUPLICATE; INVALID_DATE; INVALID_AMOUNT; MALFORMED_ROW; }` — each with `label()`,
  `import()`, `kpi()`, `review()` contributions (the table above lives here, as the single source of truth).
- **`FuelImportClassification`** — an immutable value object holding `reasons: FuelImportReason[]`, and computed
  `decision()`, `kpiEligibility()`, `reviewDecision()` via the aggregation rules. `EdkImportValidator` returns one
  per row. This **replaces** `EdkImportStatus` (which is deleted) and centralises the derivation so controllers,
  KPIs, and the UI never re-derive it.

## 5. How existing data & APIs must change

### Canonical ledger — `edk_fuel_recharges` becomes the complete financial history
- `truck_id` → **nullable** (a non-fleet transaction like `AA 463 AQ` has no fleet truck but is still money spent).
- Add `detected_plate` (string, nullable) — preserve the raw plate text even when unmatched, for reviewers.
- Add `kpi_eligible` (boolean, indexed) — the KPI filter.
- Add `needs_review` (boolean, indexed) + `reviewed_at`, `reviewed_by`, `review_note` — the review workflow.
- Add `reasons` (JSON array of reason codes) — why it's flagged.
- Keep amount/estimated_litres/occurred_at/card/holder/driver/batch as-is.

### Rejections — repurpose `edk_import_exceptions` → `edk_import_rejections`
- Holds only **REJECT** rows (malformed / impossible date / amount) with `raw_line`, `reasons`, `needs_review`,
  detected context — the audit trail for corrupt input. **`DUPLICATE`** is *counted* in the batch, not re-stored
  per row (its canonical original is the record) — avoids re-import bloat.

### `fuel_import_batches`
- Replace single-status `category_counts` with counts by **each decision** (`accepted`, `rejected`,
  `kpi_eligible`, `review_required`) plus a `reason_counts` map.

### API / preview + commit
- Preview response: per row → `{ import, kpi, review, reasons[] , detected_truck, detected_plate, … }`; summary →
  counts by decision + by reason (the Import Validation Report gains three axes instead of one).
- Commit: **ACCEPT** rows (KPI-eligible or not) → canonical with the flags; **REJECT** rows → rejections;
  duplicates counted. Upsert idempotency unchanged (UNIQUE `transaction_id, truck_id` — for null-truck rows,
  duplicates are keyed on `transaction_id` alone; see Open Question Q3).
- The React import drawer shows three summary rows (Imported / KPI-eligible / To review) + a reasons breakdown,
  instead of a single status list.

### Data migration
The feature is new and the dev DB is empty (0 canonical, 0 exceptions), so there is **no production data to
migrate** — the change is additive schema + one table rename. Had data existed, the rule would be: promote
`edk_import_exceptions` rows whose reasons are ACCEPT-reasons into `edk_fuel_recharges` (kpi_eligible=false,
needs_review per rule); keep only corrupt rows as rejections.

## 6. KPIs vs accounting/audit — three read paths, one ledger
One canonical table, three consumers filtered by the flags — never re-deriving business rules:
- **Operational Fuel KPIs (F2+):** the `FuelReadModel` reads `edk_fuel_recharges WHERE kpi_eligible = true`
  (which implies `truck_id IS NOT NULL`, active, no anomaly). Analytics therefore see **only** clean fleet fuel.
- **Accounting / audit / fraud:** read the **whole** table (no filter) — every real financial movement is present,
  including inactive-truck, non-fleet, mismatch, and account-transfer rows, each annotated with its reasons.
- **Review queue:** `WHERE needs_review = true AND reviewed_at IS NULL`. Resolving a row can flip `kpi_eligible`
  (e.g. a reviewer confirms a "mismatch" was actually legitimate) or `needs_review=false` — a controlled,
  audited override, without ever deleting the financial record.

This is the core win: **financial truth and KPI eligibility are stored independently**, so a transaction can be
permanently recorded *and* correctly excluded from fleet analytics at the same time.

## 7. Worked example — `565873234319772` / `AA 463 AQ`
Parser reads a valid date, positive amount, a card, and a `Porteur` whose plate `AA 463 AQ` matches no active
fleet truck. Validator → `reasons = [UNKNOWN_TRUCK]` → **ImportDecision ACCEPT**, **KpiEligibility NOT_ELIGIBLE**,
**ReviewDecision REQUIRED**. It is stored in `edk_fuel_recharges` with `truck_id = null`,
`detected_plate = "AA 463 AQ"`, `kpi_eligible = false`, `needs_review = true`, `reasons = ["UNKNOWN_TRUCK"]`.
Accounting sees the FCFA movement; investigators see the flag; Fuel KPIs never count it. Nothing is lost.

## 8. Risks
| Risk | Mitigation |
|---|---|
| Nullable `truck_id` weakens the "operational" table | KPIs filter `kpi_eligible = true` (⇒ truck present); the table is explicitly the *financial ledger*, documented as such |
| Reviewers could silently flip KPI eligibility | `reviewed_by`/`reviewed_at`/`review_note` audit every override; reasons are immutable, the override is additive |
| Reason→decision drift across code | the mapping lives **only** on `FuelImportReason` + `FuelImportClassification`; no consumer re-derives it |
| Duplicate keying when `truck_id` is null | see Q3 — key duplicates on `transaction_id` alone for null-truck rows |
| Migration mislabels historical exceptions | dev DB empty today → no migration; rule documented if data appears |

## 9. Open business questions
1. **Review resolution outcomes** — what actions may a reviewer take (Confirm-as-financial-only / Re-attribute to
   a truck / Mark fraud / Promote to KPI-eligible)? Defines the review workflow states.
2. **Non-fleet plates** — should `UNKNOWN_TRUCK` (a real but foreign plate, `AA 463 AQ`) and "no plate at all" be
   distinct reasons? Recommend keeping one reason + `detected_plate` text; confirm.
3. **Duplicate identity for null-truck rows** — with `truck_id` nullable, is `transaction_id` globally unique
   (recommended) so duplicates are detected without a truck? Confirm EDK guarantees unique `N transaction`.
4. **Account-transfer retention** — keep `ACCOUNT_TRANSFER` rows in the same ledger (recommended, flagged) or a
   separate treasury table? (V3 from the earlier validation report.)
5. **Estimated litres on non-operational rows** — still compute money÷price for audit, or leave null when
   NOT_ELIGIBLE? Recommend compute (audit value), never surfaced to KPIs.

## 10. Migration roadmap (once approved)
- **R-a** Define the enums + `FuelImportClassification` VO; delete `EdkImportStatus`.
- **R-b** Migration: `edk_fuel_recharges` (nullable truck_id, +kpi_eligible/needs_review/reasons/review fields/
  detected_plate); rename `edk_import_exceptions` → `edk_import_rejections`; batch counters by decision.
- **R-c** Rewrite `EdkImportValidator` to emit reasons → classification; detect the **account family** (→
  `ACCOUNT_TRANSFER`) in the parser.
- **R-d** `commitEdk`: ACCEPT→ledger (flags), REJECT→rejections, duplicates counted.
- **R-e** Preview/commit API + import drawer show the three axes + reasons; add the review queue read path.
- **R-f** Tests: one per reason + per decision-combination + the aggregation rules + the worked example.

## 11. Final recommendation
Adopt the **three-decision + reasons** model. Persist **all legitimate financial activity** in the canonical
ledger (truck_id nullable), gate **operational KPIs** on a separate `kpi_eligible` flag, and drive a **review
queue** off `needs_review` — rejecting **only objectively corrupt** rows. This preserves accounting/audit/fraud
history while keeping fleet analytics clean, and lets one transaction be simultaneously *recorded*, *excluded from
KPIs*, and *flagged for review*.

**No implementation until this redesign is approved.**
