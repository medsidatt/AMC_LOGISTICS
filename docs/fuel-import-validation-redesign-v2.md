# Fuel Import Validation — Redesign v2 (Type · Reasons · three Decisions)

> **Status: SUPERSEDED by [`fuel-import-validation-adr.md`](fuel-import-validation-adr.md)** (adds the `Source`
> axis + explicit `ClassificationPolicy` + technical/business finding split). Retained for history.
> No code was produced. Date: 2026-07-01.

## 0. Executive summary
v1 separated *persistence / KPI / review*. v2 adds the missing first axis — **what the transaction IS** — as an
input that is independent from **what is wrong with it**. The model becomes:

```
TransactionType   ──┐
                    ├──►  PersistenceDecision   (ACCEPT | REJECT)
ValidationReasons[] ┘     KpiEligibility        (ELIGIBLE | NOT_ELIGIBLE)
                          ReviewDecision        (NONE | REQUIRED)
```

Five responsibilities, never collapsed: **(1) what it is** (`TransactionType`), **(2) what's wrong**
(`ValidationReasons[]`), **(3) store it?** (`PersistenceDecision`), **(4) count it?** (`KpiEligibility`),
**(5) investigate it?** (`ReviewDecision`). The last three are *derived* from the first two by one rule table
(single source of truth). The validator **proposes**; a reviewer **decides** (override without re-import).

---

## 1. Audit of the current redesign (v1)
v1 correctly split persistence/KPI/review and made the ledger the financial source of truth. **Gap:** it still
encoded "what the row is" *inside the reason list* — account movements were modelled as the anomaly
`ACCOUNT_TRANSFER`, conflating a legitimate **business type** with an **anomaly**. Consequences:
- An account recharge has *nothing wrong with it*, yet v1 needs a "reason" to explain why it's not KPI-eligible.
- Future clean business types (reversal, station purchase, fee) would each need a fake "reason", polluting the
  anomaly vocabulary and the review logic.
- KPI-eligibility for account rows depended on reading a *reason*, but the brief requires KPIs to read **only**
  `KpiEligibility` — never reasons.

## 2. Why TransactionType must be independent from ValidationReasons
- **Different questions.** Type answers *"what business event is this?"*; reasons answer *"what's wrong?"*. A row
  can be a perfectly clean event of a non-operational type (account transfer: known, correct, simply not fuel).
- **Orthogonality.** Any type can carry any anomaly: a `FUEL_RECHARGE` may be fine or may have
  `INACTIVE_TRUCK + DRIVER_MISMATCH`; an `ACCOUNT_RECHARGE` may be clean or malformed. Folding type into reasons
  makes these combinations inexpressible.
- **Stable KPI rule.** KPI-eligibility keys off **type = FUEL_RECHARGE with no anomalies**. With type separate,
  "not fuel" is expressed by type, not by inventing an anomaly — so account/reversal rows are correctly excluded
  *without* appearing in the anomaly/review lists.
- **Extensibility.** New business events (refund, station purchase) are new *types*, added without touching the
  anomaly taxonomy or the decision logic (see §15).

## 3. Why ValidationReasons must support multiple reasons
A single transaction routinely violates several rules at once — e.g. plate not in fleet **and** the named driver
isn't assigned (`UNKNOWN_TRUCK` + `DRIVER_MISMATCH`), or malformed **and** duplicate. A single value loses all but
the first finding, hiding evidence a reviewer needs. Reasons are therefore a **set**; the three decisions are
computed as aggregates over that set, so every finding is retained and auditable.

## 4. Why Persistence, KPI eligibility, and Review are independent decisions
They optimise for **different stakeholders**: persistence serves **accounting/audit** (keep all real money
movements); KPI-eligibility serves **fleet analytics** (only clean operational fuel); review serves **operations/
fraud** (a human queue). A row is commonly ACCEPT + NOT_ELIGIBLE + REQUIRED simultaneously (the `AA463AQ` case).
Collapsing any two produces the original defect — losing financial history to protect KPIs, or forcing review on
benign account rows.

## 5. Complete domain model

```
                 ┌──────────────────────── FuelImportBatch (aggregate) ────────────────────────┐
   EDK file ─►   │  source · filename · counts(by type/reason/decision) · imported_by · time    │
                 └───────────────┬───────────────────────────────────────────────┬──────────────┘
                                 │ parse (pure)                                   │
                        ParsedRow[] (raw + fields + source_family + mode)         │
                                 │ classify                                       │
                  FuelTransactionClassification { type, reasons[] }               │
                       │ derive (rule table)                                      │
        ┌──────────────┼───────────────────────────┐                             │
   PersistenceDecision │ KpiEligibility │ ReviewDecision                          │
        │ ACCEPT                                    │ REJECT                       │
        ▼                                           ▼                             ▼
 FuelCardTransaction (canonical ledger)     FuelImportRejection (corrupt/duplicate audit)
  = financial truth + effective state
```

### Rule table (single source of truth)
**Reason contributions**
| Reason | forces REJECT | forces REVIEW |
|---|---|---|
| `UNKNOWN_TRUCK` | – | ✓ |
| `INACTIVE_TRUCK` | – | ✓ |
| `CARD_MISMATCH` | – | ✓ |
| `DRIVER_MISMATCH` | – | ✓ |
| `INVALID_DATE` | ✓ | ✓ |
| `INVALID_AMOUNT` | ✓ | ✓ |
| `MALFORMED_ROW` | ✓ | ✓ |
| `DUPLICATE_TRANSACTION` | ✓ | – |

**Type contributions**
| Type | KPI-capable | review if no reason |
|---|---|---|
| `FUEL_RECHARGE` | ✓ | – |
| `ACCOUNT_RECHARGE` | – | – |
| `ACCOUNT_TRANSFER` | – | – |
| `REVERSAL` | – | – |
| `UNKNOWN` | – | ✓ |

**Derivation**
- `PersistenceDecision` = **REJECT** if any reason forces reject; else **ACCEPT**.
- `KpiEligibility` = **ELIGIBLE** iff `type == FUEL_RECHARGE` **and** `reasons == ∅` **and** `Persistence == ACCEPT`; else **NOT_ELIGIBLE**.
- `ReviewDecision` = **REQUIRED** if any reason forces review **or** `type == UNKNOWN`; else **NONE**.

### Worked cases
| Scenario | Type | Reasons | Persist | KPI | Review |
|---|---|---|---|---|---|
| Normal fuel recharge | FUEL_RECHARGE | – | ACCEPT | ELIGIBLE | NONE |
| `AA463AQ` (card on ex-fleet truck) | FUEL_RECHARGE | INACTIVE_TRUCK | ACCEPT | NOT_ELIGIBLE | REQUIRED |
| Non-fleet plate, unassigned driver | FUEL_RECHARGE | UNKNOWN_TRUCK, DRIVER_MISMATCH | ACCEPT | NOT_ELIGIBLE | REQUIRED |
| Account recharge (cash) | ACCOUNT_RECHARGE | – | ACCEPT | NOT_ELIGIBLE | NONE |
| Card→account transfer | ACCOUNT_TRANSFER | – | ACCEPT | NOT_ELIGIBLE | NONE |
| Duplicate | (any) | DUPLICATE_TRANSACTION | REJECT | NOT_ELIGIBLE | NONE |
| Malformed line | UNKNOWN | MALFORMED_ROW | REJECT | NOT_ELIGIBLE | REQUIRED |

## 6. Aggregate ownership
- **`FuelImportBatch`** (aggregate root of an import run) — owns the parse/classify result summary and is the
  parent of the rows produced. Immutable after commit except its rollups.
- **`FuelCardTransaction`** (aggregate root of the financial ledger) — owns identity, financial facts, the
  **validator proposal** (type, reasons, proposed kpi/review) *and* the **effective state** (truck_id,
  kpi_eligible, review status) that a reviewer may change. Enforces the invariant *"a reviewer decision never
  deletes financial facts; it only adjusts effective attribution + eligibility, with an audit trail."*
- **`FuelImportRejection`** — corrupt/duplicate rows; belongs to the batch; never promoted automatically.
- **Classification** and **ReviewResolution** are **value objects** embedded on the transaction, not entities.

## 7. Value Objects (to define; not implemented here)
- `enum TransactionType { FUEL_RECHARGE; ACCOUNT_RECHARGE; ACCOUNT_TRANSFER; REVERSAL; UNKNOWN; }` — with
  `isKpiCapable()`, `requiresReviewWhenClean()`.
- `enum ValidationReason { UNKNOWN_TRUCK; INACTIVE_TRUCK; CARD_MISMATCH; DRIVER_MISMATCH; INVALID_DATE;
  INVALID_AMOUNT; DUPLICATE_TRANSACTION; MALFORMED_ROW; }` — with `forcesReject()`, `forcesReview()`, `label()`.
- `enum PersistenceDecision { ACCEPT; REJECT; }`
- `enum KpiEligibility { ELIGIBLE; NOT_ELIGIBLE; }`
- `enum ReviewDecision { NONE; REQUIRED; }`
- **`FuelTransactionClassification`** (immutable VO) — `{ TransactionType type; ValidationReason[] reasons }`,
  with derived `persistence()`, `kpiEligibility()`, `review()`. **The only place the rule table lives.** This is
  the validator's *proposal*.
- **`ReviewResolution`** (VO) — `{ reviewer_id, reviewed_at, outcome, note, assigned_truck_id?, kpi_override? }`
  where `outcome ∈ { CONFIRMED_NON_OPERATIONAL, RE_ATTRIBUTED, MARKED_FRAUD, PROMOTED_TO_KPI, DISMISSED }`.

## 8. Entity changes
`EdkImportStatus` (single enum) is **deleted**. The `EdkFuelRecharge` entity is **broadened and renamed to
`FuelCardTransaction`** (it now holds recharges *and* account movements *and* reversals — "recharge" is too
narrow). It gains: `transaction_type`, `reasons`, `kpi_eligible`, `review_status`, review fields, `detected_plate`,
nullable `truck_id`. The v1 `EdkImportException` model becomes **`FuelImportRejection`** (corrupt/duplicate only).
`FuelImportBatch` stays, with richer counters.

## 9. Database changes
Building on today's live single-status schema (dev DB empty → schema-only migration):

**`fuel_card_transactions`** (rename from `edk_fuel_recharges`; the canonical financial ledger)
- `truck_id` → **nullable** FK; add `driver_id` nullable (exists); add `detected_plate` (string, nullable).
- `transaction_type` (string, indexed); `reasons` (json).
- `kpi_eligible` (bool, indexed) — **effective**; `review_status` (enum `NONE|PENDING|RESOLVED`, indexed).
- `reviewed_at`, `reviewed_by` (FK users), `review_outcome` (string, nullable), `review_note` (text, nullable).
- **Validator proposal snapshot** (immutable audit): `proposed_kpi_eligible` (bool), `proposed_reasons` (json) —
  so an override is always comparable to the original proposal.
- keep `transaction_ref`(=transaction_id), `amount_fcfa`, `estimated_litres` (nullable), `occurred_at`,
  `card_number`, `holder_raw`, `price_per_litre`, `fuel_import_batch_id`, `imported_by`, timestamps.
- Indexes for the three read paths: `(kpi_eligible, occurred_at)`, `(review_status)`, `(truck_id, occurred_at)`.

**`fuel_import_rejections`** (rename from `edk_import_exceptions`) — `raw_line`, `line_number`, `reasons` (json),
`transaction_type`, detected context, `fuel_import_batch_id`, timestamps. Duplicates **counted in the batch**,
not stored per-row.

**`fuel_import_batches`** — `type_counts`, `reason_counts`, `decision_counts` (accepted/rejected/kpi_eligible/
review_required) as JSON.

**Uniqueness / duplicates:** with nullable `truck_id`, key duplicate detection on `transaction_ref` **alone**
(EDK `N transaction` is globally unique — see Q). Keep the DB unique on `transaction_ref`.

## 10. API changes
- **Preview** returns, per row: `{ type, reasons[], persistence, kpi_eligible, review, detected_truck,
  detected_plate, amount, estimated_litres }`; summary: counts **by type**, **by reason**, **by decision**.
- **Commit** persists ACCEPT rows to the ledger (effective = proposal), REJECT rows to rejections, counts
  duplicates; returns the three-axis report.
- **New review endpoints** (design, not built): `GET /fuel/review` (queue = `review_status = PENDING`),
  `POST /fuel/review/{transaction}/resolve` (apply a `ReviewResolution`: set truck, flip `kpi_eligible`, record
  reviewer/note/outcome, `review_status = RESOLVED`). No re-import ever required.
- The import drawer shows a **type × decision** matrix + reasons, not a single status list.

## 11. Import pipeline changes
1. **Parser (pure):** detect `source_family` (card vs account) and expose raw fields incl. `mode_de_recharge`
   (account family) and amount sign. No business decisions.
2. **Classifier** (`FuelImportClassifier`, replaces `EdkImportValidator`): assign `TransactionType` from
   family/mode/sign; detect `ValidationReason[]` from truck/card/driver/duplicate/date/amount; build the
   `FuelTransactionClassification`. It **proposes**; it does not persist.
3. **Commit:** derive the three decisions from the classification; ACCEPT → ledger, REJECT → rejections,
   duplicates counted; write the batch rollups.

## 12. Manual review workflow ("validator proposes, reviewer decides")
- On import, **effective = proposal** (`kpi_eligible = proposed_kpi_eligible`, `review_status = PENDING` when
  ReviewDecision REQUIRED else `NONE`).
- The review queue lists `review_status = PENDING`. A reviewer applies a `ReviewResolution`:
  - **RE_ATTRIBUTED:** set `truck_id` to the correct truck (e.g. identifies the vehicle behind `AA463AQ`), may set
    `kpi_eligible = true`.
  - **PROMOTED_TO_KPI / CONFIRMED_NON_OPERATIONAL / MARKED_FRAUD / DISMISSED:** adjust `kpi_eligible` + record
    `review_outcome`, `review_note`, `reviewed_by`, `reviewed_at`; `review_status = RESOLVED`.
- **Invariants:** financial facts (`amount`, `occurred_at`, `card`, `transaction_ref`) are **immutable**; the
  `proposed_*` snapshot is **immutable** (audit); only effective attribution/eligibility + review fields change.
  **No re-import is ever needed** — the row already exists; review mutates it in place.

## 13. KPI consumption workflow
- The **`FuelReadModel`** (F2) exposes recharges **only** where `kpi_eligible = true`. It **must never** read
  `reasons`, `transaction_type`, or `review_status` — the brief's hard rule. KPIs/Calculators depend on the read
  model, so they physically cannot see anomalies. Eligibility is the single, stable contract between validation
  and analytics; changing the rule table never changes the KPI query.

## 14. Accounting / audit workflow
- Accounting reads the **whole** `fuel_card_transactions` ledger (no filter) — every real movement, grouped by
  `transaction_type` (fuel vs account vs reversal), by `reason`, by `review_status`. Nothing is hidden.
- `fuel_import_rejections` gives the corrupt-input trail; `fuel_import_batches` gives the per-import provenance.
- Fraud/investigation view = `review_status IN (PENDING, RESOLVED) AND review_outcome = MARKED_FRAUD`, plus the
  raw plate on `UNKNOWN_TRUCK`/`INACTIVE_TRUCK` rows.

## 15. Future extensibility
The `TransactionType` axis is the extension seam:
- **`REVERSAL` / `REFUND`** — negative-amount or reversal-marked rows; ACCEPT, NOT_ELIGIBLE (or net against the
  original in accounting), REVIEW per rule. Already reserved in the enum.
- **`STATION_PURCHASE`** — the deferred EDK per-fill transaction export (litres + station). This type would be
  **KPI-capable** and would introduce a `FuelStation` reference and new reasons (`UNKNOWN_STATION`,
  `PRICE_ANOMALY`) — all added by extending the two vocabularies + the rule table, with **zero change** to
  persistence/KPI/review consumers.
- **`FEE` / `ADJUSTMENT`** — bank/card fees; ACCEPT, NOT_ELIGIBLE, NONE.
New types/reasons are additive rows in the rule table; the three decisions and all consumers are untouched.

## 16. Migration strategy (from the live single-status model)
The single-status model is in code; the dev DB is **empty** (0 ledger, 0 exceptions), so migration is
**schema-first, data-none**:
- **M1** Introduce VOs (`TransactionType`, `ValidationReason`, decisions, `FuelTransactionClassification`); delete
  `EdkImportStatus`.
- **M2** Migrate schema: rename `edk_fuel_recharges → fuel_card_transactions` (nullable truck_id + new columns);
  rename `edk_import_exceptions → fuel_import_rejections`; extend `fuel_import_batches` counters.
- **M3** Rewrite classifier + parser family/type detection; rewrite commit for ACCEPT/REJECT + proposal snapshot.
- **M4** API: preview/commit three-axis report; add review endpoints + queue.
- **M5** Tests: rule-table truth tables (type × reasons → decisions), per-type, per-reason, multi-reason, review
  override, the `AA463AQ` case.
- **If data ever exists** before M2: promote `edk_import_exceptions` rows whose reasons are non-corrupt into the
  ledger (kpi_eligible=false, review PENDING); keep only corrupt rows as rejections. (Not needed now.)

## 17. Open business decisions
1. **Review outcomes** — confirm the `ReviewResolution.outcome` set and whether `PROMOTED_TO_KPI` is allowed
   (can a human make a non-fleet transaction KPI-eligible, or only after RE_ATTRIBUTED to a real truck?).
2. **`UNKNOWN_TRUCK` vs `INACTIVE_TRUCK`** for `AA463AQ` — is an ex-fleet plate kept in the DB as an inactive
   truck (→ INACTIVE_TRUCK) or absent (→ UNKNOWN_TRUCK)? Decides whether retired trucks are soft-retained.
3. **Duplicate identity** — is EDK `N transaction` globally unique (so duplicates are keyed without a truck)?
4. **Reversal detection** — how is a reversal signalled in the EDK export (negative amount? a flag? a paired
   transaction)? Needed to implement `REVERSAL` correctly.
5. **Account movements retention** — same ledger with `transaction_type` (recommended) or a separate treasury
   table? (Carried from V3.)
6. **Rename scope** — approve `edk_fuel_recharges → fuel_card_transactions` (accurate) vs keep the name to
   minimise churn.
7. **Estimated litres** on non-`FUEL_RECHARGE` / non-eligible rows — compute for audit or leave null?

## Final recommendation
Adopt the **Type → Reasons → (Persistence · KPI · Review)** model. It expresses *what a transaction is*
independently from *what is wrong with it*, keeps all legitimate financial history, exposes KPIs a single stable
`kpi_eligible` contract that never inspects reasons, and gives reviewers authority to override the validator
without re-import. It also absorbs future events (reversals, station purchases, fees) by extension alone — making
it a suitable **stable target** to lock before implementation.

**No implementation until this architecture is approved.**
