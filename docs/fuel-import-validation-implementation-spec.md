# Fuel Import Validation — Implementation Specification

> **Status: build-ready spec. No code / migrations / repo changes here.** Derived from the stable
> [ADR](fuel-import-validation-adr.md); does not redesign it. Date: 2026-07-01.

## Phase 1 — Architecture consistency check

Every concept maps to exactly one owner, one responsibility, one source of truth (no concept has two owners; no
owner holds two concepts):

| Concept | Owner (target artifact) | Single responsibility | Source of truth |
|---|---|---|---|
| **TransactionType** | `App\Enums\Fuel\TransactionType` (enum) | the type vocabulary + `isKpiCapable()` | the enum |
| **Source** | `App\Enums\Fuel\FuelSource` (enum) | provenance vocabulary | the enum |
| **TechnicalValidationFinding** | `App\Enums\Fuel\TechnicalFinding` (enum) | integrity-finding vocabulary + `forcesReject()`/`forcesReview()` | the enum |
| **BusinessValidationFinding** | `App\Enums\Fuel\BusinessFinding` (enum) | operational-finding vocabulary + `forcesReview()` | the enum |
| **ClassificationPolicy** | `App\Domain\Fuel\ClassificationPolicy` (pure class) | (type, source, findings) → 3 decisions | the rule table herein |
| **PersistenceDecision** | `App\Enums\Fuel\PersistenceDecision` | ACCEPT/REJECT value | the enum |
| **KpiEligibility** | `App\Enums\Fuel\KpiEligibility` | ELIGIBLE/NOT_ELIGIBLE value | the enum |
| **ReviewDecision** | `App\Enums\Fuel\ReviewDecision` | NONE/REQUIRED value | the enum |
| **FuelCardTransaction** | `App\Models\FuelCardTransaction` + `fuel_card_transactions` | the financial ledger aggregate + effective state | the table row |
| **Review Workflow** | `App\Services\Fuel\FuelTransactionReviewService` + `fuel_transaction_review_events` | apply/record reviewer decisions | the append-only event table |

**Supporting owners (facts producers / orchestration), each single-responsibility:**
- `ValidationFindings` VO — holds the two typed finding lists (collection owner).
- `FuelTransactionClassification` VO — `{type, source, findings}`, the validator's *proposal* (immutable).
- `EdkFuelParser` / `FleetiFuelParser` — pure syntax only.
- `FuelImportClassifier` (**replaces `EdkImportValidator`**) — detect type + source + findings; **never decides**.
- `FuelImportService` — orchestrate persistence (ACCEPT→ledger, REJECT→rejections, batch rollups).
- `FuelReadModel` (F2 seam) — expose **only** `kpi_eligible = true`.

**Result:** consistent. One rule-holder (`ClassificationPolicy`); detection, decision, persistence, and review are
separate owners. No missing responsibility surfaced in the audit → **no new concept is introduced.**

## Phase 2 — Implementation breakdown (order + rationale)

| Step | Deliverable | Depends on |
|---|---|---|
| 1 | **Enums** (TransactionType, FuelSource, TechnicalFinding, BusinessFinding, PersistenceDecision, KpiEligibility, ReviewDecision, ReviewStatus, ReviewOutcome) | — |
| 2 | **Value Objects** (`ValidationFindings`, `FuelTransactionClassification`) | 1 |
| 3 | **ClassificationPolicy** (pure) + its **truth-table tests** | 1, 2 |
| 4 | **DB schema** (rename/extend tables — additive; old path still runs) | — |
| 5 | **Models** (`FuelCardTransaction`, `FuelImportRejection`, `FuelTransactionReviewEvent`; extend `FuelImportBatch`) | 4 |
| 6 | **Parser refactor** (expose `source_family` + `mode`/amount-sign; still pure) | — |
| 7 | **FuelImportClassifier** (detect type/source/findings → classification via Policy) | 2, 3, 6 |
| 8 | **FuelImportService** (commit: ACCEPT→ledger + proposal snapshot, REJECT→rejections, batch counts) | 3, 5, 7 |
| 9 | **Import controller** rewire (preview/commit → classifier + service); delete `EdkImportStatus` path | 7, 8 |
| 10 | **Review workflow** (`FuelTransactionReviewService` + endpoints + queue) | 5, 8 |
| 11 | **React** (three-axis preview report; review queue) | 9, 10 |
| 12 | **Read-model seam** (`FuelReadModel` filtering `kpi_eligible`) — contract only; KPIs remain F2 | 5 |

**Why this order minimises risk:**
- **Pure-and-leaf first (1→3).** The business rules — the highest-value, highest-risk artifact — are proven in
  isolation with zero DB/UI dependency. The Policy truth-table is locked before anything persists.
- **Reversible before irreversible.** Schema (4) is additive/rename; models (5) map it; nothing behaves
  differently yet. Detection (6→7) is characterization-tested against the frozen Policy.
- **Persistence and cutover last among backend (8→9).** The old single-status path keeps working until the
  controller switches, so a defect never corrupts the ledger mid-migration.
- **UI + review after backend proven (10→11).** Human-facing surfaces build on a verified core.
- **Each step is independently shippable and testable**, and the ledger is only written once steps 1–8 are green.

## Phase 3 — Database design (schema specification; no migrations)

### 3.1 `fuel_card_transactions` — canonical financial ledger (rename+broaden `edk_fuel_recharges`)
- **Purpose:** every accepted card/account transaction (financial truth), with effective operational attribution.
- **Owner:** `FuelCardTransaction`.
- **Relationships:** `belongsTo` Truck (nullable), Driver (nullable), FuelImportBatch, User (imported_by,
  reviewed_by); `hasMany` FuelTransactionReviewEvent.
- **Columns:**
  - *Identity/provenance:* `id`; `source` (varchar20); `transaction_type` (varchar24); `transaction_ref`
    (varchar64); `fuel_import_batch_id` (FK); `imported_by` (FK, nullable).
  - *Immutable financial facts:* `amount_fcfa` (decimal14,2, NOT NULL); `occurred_at` (timestamp, NOT NULL —
    invalid dates are rejected, never here); `card_number` (varchar32, nullable); `holder_raw` (varchar191,
    nullable); `detected_plate` (varchar32, nullable); `estimated_litres` (decimal12,2, nullable);
    `price_per_litre` (decimal8,2, nullable).
  - *Proposal snapshot (immutable, audit):* `proposed_technical_findings` (json); `proposed_business_findings`
    (json); `proposed_kpi_eligible` (bool); `policy_version` (varchar20).
  - *Effective / review-mutable:* `truck_id` (FK **nullable**); `driver_id` (FK nullable); `kpi_eligible` (bool,
    default false); `review_status` (varchar12: NONE|PENDING|RESOLVED, default NONE); `reviewed_at` (nullable);
    `reviewed_by` (FK nullable); `review_outcome` (varchar28, nullable); `review_note` (text, nullable).
  - `created_at`, `updated_at`.
- **Unique:** `transaction_ref` (global — EDK `N transaction` is unique; see Q7).
- **Indexes:** `(kpi_eligible, occurred_at)` [KPI read path]; `(review_status)` [queue]; `(truck_id,
  occurred_at)` [truck history]; `(source)`; `(transaction_type)`; `(card_number)`.
- **Nullable:** `truck_id`, `driver_id`, `card_number`, `holder_raw`, `detected_plate`, `estimated_litres`,
  `price_per_litre`, review fields, `imported_by`.
- **Audit fields:** proposal snapshot + `policy_version` + `fuel_import_batch_id` + `imported_by` +
  review-events (§3.3). **Review fields:** the effective/review block. **Proposal fields:** the snapshot block.

### 3.2 `fuel_import_rejections` — corrupt/duplicate audit (rename `edk_import_exceptions`)
- **Purpose:** rows the Policy REJECTED (technical-fatal); never enter the ledger. **Duplicates counted in the
  batch, not stored per-row** (their original is the ledger record).
- **Owner:** `FuelImportRejection` (child of the batch aggregate).
- **Columns:** `id`; `fuel_import_batch_id` (FK cascade); `source`; `transaction_type` (nullable); `technical_findings` (json);
  `reason_summary` (varchar191); `transaction_ref` (varchar64, nullable); `card_number`/`holder_raw`/`detected_plate`
  (nullable); `amount_fcfa` (nullable); `occurred_at` (nullable — may be the invalid value's raw); `raw_line`
  (text); `line_number` (int); `needs_review` (bool); timestamps.
- **Indexes:** `(fuel_import_batch_id)`, `(transaction_ref)`, `(needs_review)`. **No unique** (corrupt data).

### 3.3 `fuel_transaction_review_events` — append-only reviewer history (new)
- **Purpose:** immutable audit of every reviewer action; full reviewer history.
- **Owner:** part of the Review Workflow.
- **Columns:** `id`; `fuel_card_transaction_id` (FK cascade); `reviewer_id` (FK users); `outcome` (varchar28);
  `note` (text, nullable); `before` (json: truck_id, kpi_eligible, review_status); `after` (json same);
  `created_at` (**no `updated_at`** — append-only).
- **Indexes:** `(fuel_card_transaction_id, created_at)`.

### 3.4 `fuel_import_batches` — extend existing
- Add: `source_counts` (json), `type_counts` (json), `technical_finding_counts` (json), `business_finding_counts`
  (json), `decision_counts` (json: accepted/rejected/kpi_eligible/review_required), `policy_version` (varchar20).
- Keep: `original_filename`, `total_rows`, `imported_by`, timestamps. Rename `valid_rows→accepted_rows`,
  `exception_rows→rejected_rows` for the new vocabulary.

*Not created:* `FuelStation` (deferred until the EDK purchase export exists — ADR §7).

## Phase 4 — ClassificationPolicy — complete decision table

**Policy rules (the ONLY rule-holder). `Source` does not by itself change decisions — it constrains which
`type`/`findings` are producible and drives lineage/dedup; shown for traceability.**

- REJECT ⟺ technical findings ∩ {INVALID_DATE, INVALID_AMOUNT, MALFORMED_ROW, DUPLICATE_TRANSACTION} ≠ ∅.
- KPI ELIGIBLE ⟺ type = FUEL_RECHARGE ∧ (technical ∪ business findings) = ∅ ∧ Persist = ACCEPT.
- REVIEW REQUIRED ⟺ (business findings ≠ ∅) ∨ (technical findings ∩ {INVALID_DATE, INVALID_AMOUNT, MALFORMED_ROW} ≠ ∅) ∨ (type = UNKNOWN).

| # | Type | Source | Technical | Business | Persist | KPI | Review | Notes |
|---|---|---|---|---|---|---|---|---|
| 1 | FUEL_RECHARGE | EDK_CARD | – | – | ACCEPT | ELIGIBLE | NONE | the clean operational path |
| 2 | FUEL_RECHARGE | EDK_CARD | – | UNKNOWN_TRUCK | ACCEPT | NOT | REQUIRED | e.g. `AA463AQ` — kept as financial+fraud |
| 3 | FUEL_RECHARGE | EDK_CARD | – | INACTIVE_TRUCK | ACCEPT | NOT | REQUIRED | ex-fleet truck |
| 4 | FUEL_RECHARGE | EDK_CARD | – | CARD_MISMATCH | ACCEPT | NOT | REQUIRED | |
| 5 | FUEL_RECHARGE | EDK_CARD | – | DRIVER_MISMATCH | ACCEPT | NOT | REQUIRED | **[APPROVAL]** review vs log-only? |
| 6 | FUEL_RECHARGE | EDK_CARD | – | UNKNOWN_TRUCK + DRIVER_MISMATCH | ACCEPT | NOT | REQUIRED | multi-finding |
| 7 | ACCOUNT_RECHARGE | EDK_ACCOUNT | – | – | ACCEPT | NOT | NONE | treasury, not fuel |
| 8 | ACCOUNT_TRANSFER | EDK_ACCOUNT | – | – | ACCEPT | NOT | NONE | treasury movement |
| 9 | ACCOUNT_* | EDK_ACCOUNT | – | any business | ACCEPT | NOT | REQUIRED | anomaly on a transfer |
| 10 | REVERSAL | EDK_* | – | – | ACCEPT | NOT | NONE | **[APPROVAL]** review NONE vs REQUIRED |
| 11 | UNKNOWN | any | – | – | ACCEPT | NOT | REQUIRED | unclassifiable → human |
| 12 | any | any | DUPLICATE_TRANSACTION | – | REJECT | NOT | NONE | original is the record |
| 13 | any | any | INVALID_DATE | – | REJECT | NOT | REQUIRED | corrupt |
| 14 | any | any | INVALID_AMOUNT | – | REJECT | NOT | REQUIRED | corrupt |
| 15 | any | any | MALFORMED_ROW | – | REJECT | NOT | REQUIRED | corrupt |
| 16 | any | any | tech-fatal + business | any | REJECT | NOT | REQUIRED | reject wins over business |

**Rules requiring stakeholder approval (provisional defaults shown; changeable as a single policy row later):**
- **[A1]** `DRIVER_MISMATCH` alone → REQUIRED (default) vs log-only.
- **[A2]** `REVERSAL` review = NONE (default) vs REQUIRED; and negative amount ⇒ REVERSAL (type) vs INVALID_AMOUNT
  (reject).
- **[A3]** May `ACCOUNT_*` ever be KPI-eligible? Default **never**.
- **[A4]** Adopt candidate findings `UNASSIGNED_CARD`, `AMOUNT_OFF_TIER`, `FUTURE_DATED`? Default **not adopted**
  in v1 policy.
- **[A5]** `UNKNOWN_TRUCK` vs `INACTIVE_TRUCK` for ex-fleet plates depends on whether retired trucks are kept as
  inactive records (Q5).

## Phase 5 — Review workflow lifecycle

```
Import (file) ─► Parser(facts syntax) ─► Classifier(type,source,findings) ─► Policy(proposal)
     │ REJECT (technical-fatal)                          │ ACCEPT
     ▼                                                   ▼
 fuel_import_rejections                        fuel_card_transactions (effective = proposal)
 (needs_review flag)                                     │
                                       review_status = PENDING (if ReviewDecision REQUIRED) else NONE
                                                         ▼
                                                  Review Queue (review_status = PENDING)
                                                         ▼
                                    Reviewer decision → ReviewResolution
                                                         ▼
                              append fuel_transaction_review_events (before→after)
                              set effective (truck_id, kpi_eligible), review_status = RESOLVED
                                                         ▼
                              FuelReadModel (kpi_eligible = true only) ─► Fuel KPIs (F2) ─► Dashboard
```

- **States:** `review_status ∈ {NONE, PENDING, RESOLVED}`. NONE = no review needed; PENDING = queued; RESOLVED =
  human-decided.
- **Immutable:** financial facts + proposal snapshot + `policy_version`. **Mutable by review only:** `truck_id`,
  `driver_id`, `kpi_eligible`, `review_status`, `reviewed_*`, `review_outcome`, `review_note`.
- **Override behaviour:** a resolution may `RE_ATTRIBUTED` (set correct truck, optionally promote),
  `PROMOTED_TO_KPI`, `CONFIRMED_NON_OPERATIONAL`, `MARKED_FRAUD`, `DISMISSED`. Every change appends a review-event
  with before/after — the validator's proposal is never overwritten.
- **Replay behaviour:** re-running Classifier+Policy against **current** reference data recomputes findings +
  proposal (new `policy_version`). Replay updates the **proposal snapshot** for comparison but **never** changes
  the effective state of `RESOLVED` rows; for `PENDING`/`NONE` rows it may refresh the proposed decision (e.g. a
  truck retired after import flips proposal to REVIEW). Replay is idempotent and side-effect-free on financial
  facts.
- **Re-import behaviour:** idempotent on `transaction_ref` (UNIQUE). A re-imported row **never** resets review
  state or effective attribution; identical financial facts → no-op; provider-corrected facts → logged update of
  immutable-source fields only. **No re-import is ever required to fix a misclassification** — review does it in
  place.

## Phase 6 — Implementation risks & mitigations
| Risk | Mitigation |
|---|---|
| **Data loss** — legitimate financial rows dropped | ACCEPT persists all non-corrupt rows; only technical-fatal → rejections (also stored); characterization test asserts `accepted + rejected + duplicates == total` per file. |
| **Duplicates** — double financial rows | UNIQUE `transaction_ref`; commit uses upsert; DUPLICATE finding on re-see; concurrency covered below. |
| **Replay** — erasing human decisions | replay writes only the proposal snapshot; `RESOLVED` effective state is immutable to replay; policy_version records which policy produced each proposal. |
| **Performance** — classification/read cost | volumes are tiny (EDK files ≤ ~220 rows, monthly; 8 trucks). Reference data (trucks/drivers/assignments/card-owners/existing-refs) preloaded once per batch (O(n)); KPI read path indexed on `(kpi_eligible, occurred_at)`. |
| **Migration** — rename/nullable change on a live table | dev DB empty (0 rows) → schema-first, data-none; migration is additive + rename; `down()` reverses; if data ever exists, documented promotion rule (ADR §16). |
| **Concurrency** — two commits of the same preview / double-click | wrap commit in a DB transaction; rely on UNIQUE `transaction_ref` (second insert upserts, never duplicates); single-use cache token for the preview; batch creation + upsert atomic. |
| **Reference-data race** — truck edited mid-import | findings are point-in-time by design; review + replay reconcile; acceptable and auditable. |

## Phase 7 — Test plan (characterization + unit + e2e)
- **Parser tests:** EDK card row → fields; **June "Jui"** date; EDK account row (mode column) exposed as
  account-family; malformed line (<6 cols) skipped/flagged; multi-space dates; footer/header skipped. Fleeti:
  Volume2 vs Carburant vs legacy layout + ownership (existing, keep green).
- **Classification (facts) tests:** type detection per source (card→FUEL_RECHARGE; account+mode→ACCOUNT_RECHARGE/
  TRANSFER; negative→REVERSAL per [A2]); source detection per family; technical findings (invalid date/amount,
  malformed, duplicate vs canonical + within-batch); business findings (unknown/inactive/card/driver);
  multi-finding accumulation; every row produces a classification.
- **Policy tests (truth table):** assert each row of the Phase-4 matrix — every (type × technical × business) →
  exact (persist, kpi, review); reject-wins-over-business; UNKNOWN→review; empty-findings-FUEL→eligible; property
  test "KPI ELIGIBLE ⇒ type=FUEL_RECHARGE ∧ no findings ∧ ACCEPT".
- **Persistence tests:** ACCEPT→ledger with proposal snapshot + policy_version; REJECT→rejections; duplicates
  counted not stored; batch counters (source/type/finding/decision) correct; UNIQUE `transaction_ref` idempotent;
  nullable `truck_id` for UNKNOWN_TRUCK; `accepted+rejected+dupes == total`.
- **Review tests:** PENDING set when REQUIRED; resolve RE_ATTRIBUTED sets truck + flips kpi_eligible; review-event
  appended with before/after; financial facts + proposal snapshot immutable; only effective fields change;
  reviewer history ordered.
- **Replay tests:** retired-truck-after-import flips PENDING proposal; RESOLVED row unchanged by replay;
  policy_version bump; idempotent; financial facts untouched.
- **Import (e2e, real files):** CARD May (218 accept + 1 unknown), CARD Jul (72 accept + 2 unknown — post-Jui),
  ACCOUNT files (all ACCOUNT_* accepted, KPI-not, review-none — **not** UNKNOWN_TRUCK), integrity per file,
  idempotent re-import.
- **Regression tests:** Fleeti import/ownership/idempotency unchanged; `FuelComparisonService` still runs on the
  new ledger; existing 315 tests stay green; `tsc` 0 errors.
- **End-to-end:** upload → preview (three-axis report) → commit → review queue → resolve → row becomes
  kpi_eligible → `FuelReadModel` returns it (KPI seam).

## Phase 8 — Go / No-Go

**GO — implementation can begin.** The architecture is stable and every concept has one owner (Phase 1). The
open business questions do **not** block: by design they are individual **policy rows** (Phase 4 [A1]–[A5]),
changeable later without structural impact, and the schema-affecting ones have safe recommended defaults assumed
in this spec (stated below for veto).

**Assumed defaults (veto before Step 4/schema if wrong):**
- **[S1]** Rename `edk_fuel_recharges → fuel_card_transactions` (Q8) — **assumed yes**.
- **[S2]** Account movements live in the same ledger with `transaction_type` (Q6) — **assumed yes**.
- **[S3]** `transaction_ref` is globally UNIQUE (Q3/Q7) — **assumed yes** (file evidence).
- **[S4]** Ex-fleet plates: no inactive record kept ⇒ `UNKNOWN_TRUCK` (Q5) — **assumed** (INACTIVE_TRUCK reserved
  for when retired trucks are retained).

**Provisional policy cells (implement with defaults, flagged, resolve in parallel):** [A1]–[A5].

**Roadmap on approval (matches Phase 2):**
`R1` enums → `R2` VOs → `R3` ClassificationPolicy + truth-table tests → `R4` schema → `R5` models → `R6` parser
family/type exposure → `R7` FuelImportClassifier → `R8` FuelImportService (commit) → `R9` controller cutover +
delete `EdkImportStatus` → `R10` review workflow + endpoints/queue → `R11` React three-axis preview + review UI →
`R12` `FuelReadModel` seam (KPIs remain F2). Each `Rn` independently shippable with its tests; the ledger is
written only from `R8`, after the Policy is locked.

**No implementation until this spec + the [S1]–[S4] defaults are approved.**
