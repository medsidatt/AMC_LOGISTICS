# Fuel Import Validation — Architecture Decision Record (six-concept model)

> **Status: ADR for review. Architecture only — no code, migration, schema, parser, validator, controller, or
> React change is produced here.** Canonical validation architecture; supersedes
> [`fuel-import-validation-redesign-v2.md`](fuel-import-validation-redesign-v2.md). Date: 2026-07-01.

Pipeline this ADR ratifies:
```
Parser ─► (TransactionType · Source · ValidationFindings[]) ─► ClassificationPolicy
       ─► (PersistenceDecision · KpiEligibility · ReviewDecision) ─► FuelCardTransaction
       ─► Review Workflow ─► Fuel Read Models ─► Fuel KPI Calculators ─► Dashboard
```

---

## 1. Repository audit (current state, verified)

### 1.1 EDK (financial) ingestion — the stream being redesigned
| Responsibility | Lives in | Notes |
|---|---|---|
| CSV parsing (pure) | `app/Services/Fuel/EdkFuelParser.php` | syntax only; emits rows `{line, raw, txn_id, occurred_at, date_ok, montant, carte, porteur}`. **Does not** detect source family or type. |
| Classification (single status) | `app/Services/Fuel/EdkImportValidator.php` | assigns ONE `EdkImportStatus`; **also decides** persistence (only VALID persists) — the coupling to remove. |
| Status vocabulary | `app/Enums/EdkImportStatus.php` | `VALID · UNKNOWN_TRUCK · INACTIVE_TRUCK · CARD_MISMATCH · DRIVER_MISMATCH · DUPLICATE · MANUAL_REVIEW`. |
| Canonical row | `app/Models/EdkFuelRecharge.php` → `edk_fuel_recharges` | `truck_id` **NOT NULL**; `estimated_litres`; UNIQUE `(transaction_id, truck_id)`. VALID-only. |
| Quarantine | `app/Models/EdkImportException.php` → `edk_import_exceptions` | every non-VALID row, **outside** canonical. |
| Import audit | `app/Models/FuelImportBatch.php` → `fuel_import_batches` | counts keyed by single status. |
| Orchestration | `app/Http/Controllers/FuelImportController.php` | `preview/commit edk`, `preview/commit fleeti`, `index/export/showEdk/showFleeti`. Persistence + quarantine logic lives here. |
| Reconciliation | `app/Services/Fuel/FuelComparisonService.php` | monthly EDK-estimated-litres vs Fleeti-consumed (budget-vs-burn). |

### 1.2 Fleeti (operational consumption) ingestion — adjacent, not this stream
| Responsibility | Lives in |
|---|---|
| Excel parsing + format ownership (Volume 2.0 vs Carburant vs legacy) | `app/Services/Fuel/FleetiFuelParser.php` |
| Canonical daily record | `app/Models/FleetiDailyRecord.php` → `fleeti_daily_records` (UNIQUE `truck_id,record_date`) |
| Commit / partial-field merge | `FuelImportController::commitFleeti` |

### 1.3 Live telemetry (real-time) — separate pipeline, out of scope
`FleetiSyncService` → `FuelTracking`/`FuelEvent` → `TheftIncident`/`DailyDispatchEvent`; `FuelTrackingService`,
`FuelEventDetectorService`, `FleetiService`, `FleetiSyncController`, sync commands.

### 1.4 Domain / KPI layer (mostly unbuilt)
`Domain/Operations/Calculations/FuelCalculator.php` (only `yieldPerTonne`), `Contracts/FuelCalculatorInterface`,
`Events/FuelConsumptionAbnormal`, `KpiDataSource::FUEL` → resolves **null** (no `FuelReadModel`). **No** Fuel KPI in
the registry, **no** Fuel dashboard/command-center.

### 1.5 React
`pages/fuel/Index.tsx` (workspace, EDK/Fleeti tabs), `components/{FuelImportDrawer,FuelDetailsDrawer,FuelFilters}`,
`components/truck/FuelComparisonSection.tsx`, and `pages/analytics/Fleeti.tsx` (existing Fleeti report). No KPI UI.

### 1.6 Migrations (9)
truck fuel fields ×2, `fuel_trackings` (+enrich), `fuel_events`, `edk` create → rename-to-recharges, `fleeti_daily_records`, `fuel_import_validation_tables`.

### 1.7 Existing validation rules (where they live today)
- **Technical:** date parse (`EdkFuelParser::parseDate`), amount>0 & malformed skip (parser/validator), duplicate
  (`EdkImportValidator` vs canonical + batch), Fleeti zero-activity skip (`FleetiFuelParser`).
- **Business:** truck match active-only, inactive/unknown, card→truck history, driver-vs-assignment
  (`EdkImportValidator`); Fleeti format ownership (`FleetiFuelParser`).
- **All of these currently also make persistence/KPI/review decisions implicitly** — the coupling this ADR removes.

## 2. Problems in the current model
1. **Detection and decision are fused.** `EdkImportValidator` both *detects* anomalies and *decides* persistence
   (only VALID persists). There is no independent policy.
2. **No `Source` axis.** Provenance (EDK card vs account vs Fleeti vs API vs manual) is implicit in which code
   path ran, so it can't be queried, audited, or used by policy, and multi-provider is unmodelled.
3. **`TransactionType` is missing / faked.** Account movements are mislabelled `UNKNOWN_TRUCK`; "what it is" is
   conflated with "what's wrong."
4. **Findings are single + untyped.** One status can't hold co-occurring findings, nor separate *technical*
   (data-integrity) from *business* (operational) concerns.
5. **Financial history is lost.** Legitimate non-operational transactions (ex-fleet truck, account transfer) are
   quarantined out of the canonical ledger.
6. **No policy versioning / replay.** A row's classification can't be recomputed or explained against a versioned
   policy; reviewer overrides and validator proposals aren't separated.

## 3. New architecture — six independent concepts

**Detection layer (facts about the row) — produced by parser + classifier, never decisions:**
1. **`TransactionType`** — *what business event is this?*
2. **`Source`** — *where did it come from?*
3. **`ValidationFindings[]`** — *what is true/wrong about it?* (technical + business, multiple)

**Policy layer (a pure function of the three facts):**
4. **`ClassificationPolicy`** — `(type, source, findings[]) → (persistence, kpi, review)`. Deterministic,
   versioned, the **single** place business rules live. Detection never calls it; consumers never re-derive it.

**Decision layer (outcomes):**
5. **`PersistenceDecision`** (ACCEPT | REJECT) · **`KpiEligibility`** (ELIGIBLE | NOT_ELIGIBLE) ·
   **`ReviewDecision`** (NONE | REQUIRED).

### 3.1 TransactionType (justified values)
`FUEL_RECHARGE` (card recharge intended as truck fuel) · `ACCOUNT_RECHARGE` (master-account top-up, e.g. cash) ·
`ACCOUNT_TRANSFER` (card↔account movement) · `REVERSAL` (returned/negated transaction — reserved) · `UNKNOWN`
(unclassifiable). Only `FUEL_RECHARGE` is ever KPI-capable.

### 3.2 Source (justified values)
`EDK_CARD` (`histo_rechaerge_compte_carte*`) · `EDK_ACCOUNT` (`histo_rechaerge_compte*`) · `FLEETI_VOLUME`
(Volume de carburant 2.0) · `FLEETI_TANK` (Carburant) · `API` (live telemetry / future ERP push) · `MANUAL`
(human/reviewer-created) · `CSV` (generic/other import). **Independence from type:** one source carries several
types (`EDK_ACCOUNT` → `ACCOUNT_RECHARGE` + `ACCOUNT_TRANSFER`); one type arrives from several sources
(`FUEL_RECHARGE` from `EDK_CARD` today, `API`/`MANUAL` tomorrow). Source drives lineage, dedup strategy, trust,
and multi-provider growth — not business meaning. *Scope note:* `TransactionType` applies to the **transaction**
stream (`FuelCardTransaction`); `FLEETI_*`/consumption records are a separate aggregate that reuses `Source` +
`ValidationFindings` + the three decisions with a fixed implicit type (`CONSUMPTION`) — see §7.

### 3.3 ValidationFindings — two independent categories
- **Technical** (data integrity; can force REJECT): `INVALID_DATE`, `INVALID_AMOUNT`, `MALFORMED_ROW`,
  `DUPLICATE_TRANSACTION`. *(Candidates after file audit, needs confirmation:* `FUTURE_DATED`, `ENCODING_ERROR`,
  `MISSING_REQUIRED_FIELD`.)
- **Business** (operational context; never reject, may gate KPI/review): `UNKNOWN_TRUCK`, `INACTIVE_TRUCK`,
  `DRIVER_MISMATCH`, `CARD_MISMATCH`. *(Candidates from real files, needs confirmation:* `UNASSIGNED_CARD`
  (card never seen before), `AMOUNT_OFF_TIER` (montant not a known recharge tier), `DRIVER_UNRESOLVED` (no driver
  parsed — advisory only).)
- **Why split:** technical = "can we trust the bytes"; business = "does it fit our operations." Only technical
  findings may reject; business findings preserve financial truth. The split makes the policy's REJECT rule
  trivial and keeps the vocabularies independently extensible.

### 3.4 Is the parser→facts→policy→decisions separation sufficient? (Phase 3)
**Yes, with one caveat to design for.** Findings are **point-in-time facts** computed against reference data at
import (truck active? card owner? txn seen?). If reference data later changes (a truck is retired *after* import),
the stored finding is stale. The policy is pure given findings, so the fix is **replay**: recompute findings
against current data on demand (§6), producing a new *proposed* classification while preserving human overrides.
The separation is sufficient **provided** we (a) version the policy, (b) store the validator proposal separately
from the effective/review state, and (c) support replay. All three are in this design.

## 4. Decision matrix (Phase 4)

Policy rules (derived from current implementation + real files; **no invented rules**):
- **Persistence = REJECT** iff findings ∩ {`INVALID_DATE`, `INVALID_AMOUNT`, `MALFORMED_ROW`,
  `DUPLICATE_TRANSACTION`} ≠ ∅. Else **ACCEPT**.
- **KpiEligibility = ELIGIBLE** iff `type = FUEL_RECHARGE` **and** findings = ∅ **and** Persistence = ACCEPT. Else
  **NOT_ELIGIBLE**.
- **ReviewDecision = REQUIRED** iff (findings ∩ {business ∪ non-duplicate technical} ≠ ∅) **or** `type = UNKNOWN`.
  Else **NONE**.

| Type | Findings | Persist | KPI | Review |
|---|---|---|---|---|
| FUEL_RECHARGE | ∅ | ACCEPT | ELIGIBLE | NONE |
| FUEL_RECHARGE | UNKNOWN_TRUCK | ACCEPT | NOT_ELIGIBLE | REQUIRED |
| FUEL_RECHARGE | INACTIVE_TRUCK | ACCEPT | NOT_ELIGIBLE | REQUIRED |
| FUEL_RECHARGE | UNKNOWN_TRUCK + DRIVER_MISMATCH | ACCEPT | NOT_ELIGIBLE | REQUIRED |
| FUEL_RECHARGE | CARD_MISMATCH | ACCEPT | NOT_ELIGIBLE | REQUIRED |
| ACCOUNT_RECHARGE | ∅ | ACCEPT | NOT_ELIGIBLE | NONE |
| ACCOUNT_TRANSFER | ∅ | ACCEPT | NOT_ELIGIBLE | NONE |
| REVERSAL | ∅ | ACCEPT | NOT_ELIGIBLE | NONE* |
| UNKNOWN | ∅ | ACCEPT | NOT_ELIGIBLE | REQUIRED |
| *(any)* | DUPLICATE_TRANSACTION | REJECT | NOT_ELIGIBLE | NONE |
| *(any)* | INVALID_DATE / INVALID_AMOUNT | REJECT | NOT_ELIGIBLE | REQUIRED |
| *(any)* | MALFORMED_ROW | REJECT | NOT_ELIGIBLE | REQUIRED |
| ACCOUNT_* | + business finding | ACCEPT | NOT_ELIGIBLE | REQUIRED |

**Rules requiring stakeholder confirmation** (flagged, not assumed): (a) does `DRIVER_MISMATCH` *alone* warrant
review or only log? (b) `REVERSAL` review = NONE or REQUIRED (*)? (c) is a negative amount a `REVERSAL` (type) or
`INVALID_AMOUNT` (reject)? (d) may `ACCOUNT_*` ever be KPI-eligible (recommended: never)? (e) should
`UNASSIGNED_CARD`/`AMOUNT_OFF_TIER` be findings at all? (f) `UNKNOWN_TRUCK` vs `INACTIVE_TRUCK` depends on whether
ex-fleet trucks are retained as inactive records.

## 5. Storage model (Phase 5 — design only, no migrations)

**`fuel_card_transactions`** (the canonical financial ledger; broadened rename of `edk_fuel_recharges`):
- **Immutable financial facts:** `transaction_ref` (UNIQUE), `source`, `transaction_type`, `amount_fcfa`,
  `occurred_at`, `card_number`, `holder_raw`, `detected_plate`, `estimated_litres`, `price_per_litre`,
  `fuel_import_batch_id`, `imported_by`.
- **Validator proposal snapshot (immutable, audit):** `proposed_findings` (json), `proposed_kpi_eligible`,
  `policy_version`.
- **Effective / mutable-by-review:** `truck_id` (**nullable** FK), `driver_id` (nullable), `kpi_eligible`
  (indexed), `review_status` (`NONE|PENDING|RESOLVED`, indexed), `reviewed_at`, `reviewed_by`, `review_outcome`,
  `review_note`.
- Indexes for the three read paths: `(kpi_eligible, occurred_at)`, `(review_status)`, `(truck_id, occurred_at)`.

**Separate tables:**
- **`fuel_import_rejections`** (rename of `edk_import_exceptions`) — only REJECT rows (technical-fatal); `raw_line`,
  `findings`, `source`, `transaction_type`, detected context, batch. **Duplicates counted in the batch, not stored
  per row** (their original is the ledger record).
- **`fuel_transaction_review_events`** — append-only reviewer history (see §6). Keeps full audit even across
  multiple reviews; the transaction row holds only the *current* effective state.
- **`fuel_import_batches`** — extend counters to `source_counts`, `type_counts`, `finding_counts`,
  `decision_counts`, `policy_version`.

**Do findings need their own table?** No for the transaction stream — a json array on the row plus the append-only
review-events table is sufficient (findings have no independent lifecycle; resolution happens at the transaction
level). A `fuel_transaction_findings` child table is only justified if a single finding must be resolved
independently — **not** required by current needs.

## 6. Review workflow (Phase 6) — "validator proposes, reviewer decides"
- **Immutable forever:** `transaction_ref`, `source`, `amount_fcfa`, `occurred_at`, `card_number`, `holder_raw`,
  `raw`, the **proposal snapshot** (`proposed_findings`, `proposed_kpi_eligible`, `policy_version`). Financial
  facts and the validator's original proposal are never altered.
- **Mutable by review:** `truck_id`, `driver_id`, `kpi_eligible`, `review_status`, `reviewed_*`, `review_outcome`,
  `review_note`.
- **Audit trail:** every resolution appends a `fuel_transaction_review_events` row `{transaction, reviewer_id, at,
  outcome, note, before→after (truck_id, kpi_eligible)}`. Full **reviewer history** is the ordered event list; the
  transaction shows the latest effective state.
- **Outcomes** (to confirm): `RE_ATTRIBUTED` (set correct truck, maybe promote), `PROMOTED_TO_KPI`,
  `CONFIRMED_NON_OPERATIONAL`, `MARKED_FRAUD`, `DISMISSED`.
- **Replay behaviour:** re-running the classifier/policy against **current** reference data recomputes findings +
  proposal (new `policy_version`). Replay **updates the proposal snapshot only for rows not yet human-resolved**;
  `RESOLVED` rows keep their human effective state (proposal snapshot may still be refreshed for comparison, never
  the effective decision). This lets policy evolution re-classify the backlog without erasing decisions.
- **Re-import behaviour:** idempotent on `transaction_ref`. A re-imported row **never resets review state or
  effective attribution**; it may only refresh immutable-source financial facts if the provider corrects them
  (logged). No re-import is ever required to fix a misclassification — review handles it in place.

## 7. Future extensibility (Phase 7) — supported by extension, not redesign
| Future need | How it slots in |
|---|---|
| **EDK purchase export** (litres + station) | new `Source` = `EDK_PURCHASE`; new `TransactionType` = `STATION_PURCHASE` (**KPI-capable**); new findings `UNKNOWN_STATION`, `PRICE_ANOMALY`; new `FuelStation` reference. Policy = one new rule row. |
| **Station purchases / refunds / reversals** | `TransactionType` `STATION_PURCHASE` / `REFUND` / `REVERSAL` (reserved). |
| **Multiple providers** | new `Source` values (e.g. `TOTAL_CARD`, `SHELL_CARD`); same types/findings/policy. |
| **ERP / API integration** | `Source` = `API`; same pipeline; provider adapter feeds the parser stage. |
| **Manual adjustments** | `Source` = `MANUAL`, `TransactionType` = `ADJUSTMENT`, created by a reviewer through the review workflow. |
| **Consumption stream (Fleeti)** | reuses `Source` + `ValidationFindings` + three decisions on `FleetiDailyRecord`; fixed type `CONSUMPTION`; KPI-eligible iff active truck + no findings. |

The six axes are the stable seams: new behaviour is **additive enum values + policy rows**, with zero change to
persistence/KPI/review consumers or the read models.

## 8. Responsibility ownership (target)
| Concern | Owner |
|---|---|
| Syntax parsing | `EdkFuelParser` / `FleetiFuelParser` (pure) |
| Source + Type detection | `FuelImportClassifier` (replaces `EdkImportValidator`) |
| Findings detection (technical + business) | `FuelImportClassifier` (+ reference-data readers) |
| Facts → decisions | **`ClassificationPolicy`** (pure, versioned) — the only rule-holder |
| Persistence orchestration | controller/import service (ACCEPT→ledger, REJECT→rejections) |
| Effective state + overrides | `FuelCardTransaction` aggregate + review service |
| KPI exposure | `FuelReadModel` — reads **only** `kpi_eligible` (never findings/type/source) |
| Accounting/audit | full-ledger reads + rejections + batches + review-events |

## 9. Open business decisions (need stakeholder input)
1. Review outcome set + whether `PROMOTED_TO_KPI` is allowed without `RE_ATTRIBUTED`.
2. `DRIVER_MISMATCH` alone → review or log-only? `REVERSAL` → review or none?
3. Negative amount → `REVERSAL` (type) vs `INVALID_AMOUNT` (reject)? How does EDK signal a reversal?
4. Extra findings — adopt `UNASSIGNED_CARD`, `AMOUNT_OFF_TIER`, `FUTURE_DATED`? (recharge tiers 210 000/238 000
   observed — is "off-tier" an anomaly?)
5. `UNKNOWN_TRUCK` vs `INACTIVE_TRUCK` — are ex-fleet trucks retained as inactive records?
6. Account movements — same ledger with `transaction_type` (recommended) vs separate treasury table?
7. Duplicate identity when `truck_id` null — is `transaction_ref` globally unique? (evidence: yes in files.)
8. Rename `edk_fuel_recharges → fuel_card_transactions` — approve or keep name?
9. Policy versioning + replay cadence — on-demand only, or scheduled re-classification of `PENDING` rows?

## 10. Risks
| Risk | Mitigation |
|---|---|
| Nullable `truck_id` weakens "operational" table | KPI read path filters `kpi_eligible = true` (⇒ truck present + FUEL_RECHARGE); table is documented as the financial ledger. |
| Stale findings after reference-data change | replay recomputes findings vs current data; proposal snapshot + policy_version make it auditable. |
| Policy drift across consumers | rules live **only** in `ClassificationPolicy`; consumers read decisions, never re-derive. |
| Reviewer silently changing eligibility | append-only review-events + immutable proposal snapshot audit every override. |
| Over-generalising Source across two aggregates | scope is explicit (§3.2/§7): TransactionType = transaction stream; Fleeti/consumption is a separate aggregate. |
| Continued redesign churn | this six-concept model is extension-closed (§7) — recommend locking it as the target. |
| Growing enum/finding vocabulary | additive by design; policy is a table, not branching code. |

## 11. Recommendation
Adopt the **six-concept** model: **detect** `TransactionType`, `Source`, and typed `ValidationFindings[]`
(technical vs business) in the classifier; **decide** `PersistenceDecision` / `KpiEligibility` / `ReviewDecision`
exclusively in a pure, versioned **`ClassificationPolicy`**; persist **all legitimate financial activity** in
`FuelCardTransaction` (truck_id nullable) with an immutable proposal snapshot; give reviewers authority to override
via an append-only audit trail **without re-import**; and expose KPIs a single `kpi_eligible` contract that never
inspects findings. This cleanly separates the five (now six) responsibilities, preserves accounting/audit/fraud
history, keeps analytics clean, and absorbs future providers, station purchases, refunds, ERP/API, and manual
adjustments by extension alone.

Because the model is extension-closed for the foreseeable roadmap, **recommend locking this ADR as the target
architecture** and, on approval, proceeding to a phased implementation (VOs + policy → schema → classifier →
commit → review workflow → read model). The §9 questions can be resolved in parallel — most affect only individual
policy rows, not the architecture.

**No implementation until this ADR is approved.**
