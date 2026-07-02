# Fuel Domain — EDK Reclassification (Architecture Decision Record)

> **Status: DRAFT for review. No implementation.** No code, migration, importer, Read Model, Calculator,
> KPI, dashboard, reconciliation, or DB change is produced by this document.
> Companion to [`fuel-domain-architecture.md`](fuel-domain-architecture.md); it **supersedes that document's
> EDK classification** where they differ. Governed by the
> [Operational Intelligence architecture (FROZEN)](operational-intelligence-architecture.md).
> Date: 2026-07-01.

## 0. The question, answered in one line

**The EDK _platform_ is a fuel-card system with partner stations — accepted. But the EDK _reports AMC actually
exports today_ are card/account _recharge_ ledgers, not fuel-purchase transactions.** With the data in hand, EDK
is a **financial/recharge source**, not an authoritative operational purchase source. It can *become* the
purchase source **only** when a *different* EDK export — the per-fill transaction/consumption report carrying
station + litres — is obtained. That export does not exist in the repository or the source folder today.

This is not a semantic quibble: every downstream decision (FuelPurchase entity, FuelStation entity, station
reconciliations, "purchased vs consumed" KPI) hinges on it, and all of them stay **blocked** until the purchase
export is produced.

---

## 1. EDK Classification (Phase 1)

I inspected **all four** EDK exports in the source folder (not only the one named in the brief). They fall into
**two distinct report families**, both titled *"historique rechargement"* (recharge history):

| Family | Files | Columns | What a row is |
|---|---|---|---|
| **R-1 · Account recharge** | `histo_rechaerge_compte_20260618`, `…_20260701` | `ID Transaction; N transaction; Date; Montant; `**`Mode de recharge`**`; `**`Commentaires`** | A money movement **into the EDK account** — `Mode de recharge` ∈ {*"Rechargement par Espèces"* (cash top-up), *"Transfert carte vers Compte"* (card→account transfer)}. |
| **R-2 · Card recharge** | `histo_rechaerge_compte_carte_20260513` (219 rows, Jan–May), `…_carte_20260701` (74 rows) | `ID Transaction; N transaction; Date; Montant; `**`Numero carte`**`; `**`Porteur`** | A recharge **onto a specific card**, card identified by `Numero carte`, truck/driver embedded in free-text `Porteur`. |

**Verdict — Outcome (A) Card / account recharge, for both families.** Evidence, all from the actual columns:

1. **No `Station`, `Merchant`, `Réseau`, `POS`, `Terminal`, `Location`, `Litres`, `Volume`, or `Quantité`
   column exists in any of the four files** (verified by token scan — the only "total" is the `Montant Total`
   footer). A fuel *purchase* report cannot omit the station and the litres.
2. **Family R-1 self-labels every row as a recharge/transfer** via `Mode de recharge`. That is definitional.
3. **Amounts cluster on a few round tiers, not a continuous distribution.** In the 219-row card file:
   `210 000` ×156 and `238 000` ×47 = **93%** of rows at two values; the recharge exports even include `10 / 20
   / 60` FCFA adjustments. Fuel fills priced at pump (`litres × price`, gated by tank level) do **not** repeat
   the identical amount 156 times — standardized top-ups do.
4. **Cadence ≈ one event per card every ~4–5 days** (9 cards, ~23–31 events each over ~4 months) — a top-up
   rhythm for corridor trucks, not a per-fuel-stop rhythm.
5. **Filenames** literally read *rechargement compte / compte_carte* (recharge of account / card).

> **Reconciling with the new business info:** EDK-as-a-platform genuinely manages cards used at partner stations
> — that is true and unchanged. It simply means EDK is *capable* of producing a purchase/transaction export.
> The files we hold are the **recharge** side of that platform, not the **spend/dispense** side. The correct
> conclusion is therefore *conditional*, not a flat "recharge only": **EDK is a recharge source today; obtaining
> its transaction export upgrades it to the authoritative purchase source (see §12).**

---

## 2. Transaction Analysis (Phase 2)

**Family R-2 (card recharge) — the one the existing `EdkFuelParser` consumes:**

| Column | Business meaning | Owner | Mandatory | Nullable | Importance | Relationship to fuel ops |
|---|---|---|---|---|---|---|
| `ID Transaction` | **Always `0`** — a placeholder, not an identifier | EDK | — | — | **Discard** | none |
| `N transaction` | Unique recharge reference | EDK | Yes | No | **Primary key** | dedupe key |
| `Date` | Recharge timestamp (to the second) | EDK | Yes | No | High | when money was loaded (≠ when fuel burned) |
| `Montant` | Amount loaded (FCFA) | EDK/Finance | Yes | No | High | **money onto card** — not litres |
| `Numero carte` | Physical card number | EDK | Yes | No | High | card→truck link (via registry/`Porteur`) |
| `Porteur` | Free-text: plate + driver name | Ops (de facto) | Yes | No | Medium | truck & driver attribution (fuzzy) |

**Family R-1 (account recharge)** replaces `Numero carte`/`Porteur` with `Mode de recharge` (the recharge
channel) and `Commentaires` (free note, e.g. *"huitaine"*). It has **no card and no truck** — it is a pure
treasury movement on the master account.

**Direct answers:**
- *Does one row represent one fuel purchase?* **No** — no station, no litres, amounts are fixed tiers.
- *One recharge?* **Yes** — a top-up of a card (R-2) or the account (R-1).
- *An accounting movement?* **Yes** — especially R-1 (cash top-up, card→account transfer, small adjustments).
- *A card movement?* **R-2 yes** (money onto a named card); **R-1 no** (account-level).

---

## 3. Gas-Station Analysis (Phase 3)

**No station data exists in any EDK export.** No `Station`, `Merchant`, `Network`, `Provider`, `Terminal`,
`POS`, or `Location` field is present (token-scan verified across all four files).

**Decision: `FuelStation` is NOT a justified entity with the current data.** Creating it now would be
speculative schema (violates "reuse before create / justify every field"). It becomes justified **only** if the
EDK transaction/purchase export (which typically carries the station/merchant per fill) is obtained — at which
point `FuelStation` is a legitimate reference entity (§4, §12).

---

## 4. Card Analysis (Phase 4)

Evidence from R-2: **~8–9 stable card numbers** (`37004780201406`…`413`, plus rare `…199354/199356`), each
recurring for months, each `Porteur` consistently naming **the same truck plate** (e.g. card `…201409` ↔ *"Salif
Niang 6081TTA1"* across the whole span). Driver names occasionally vary within a card.

**Cards are: permanent · account-based · truck-anchored (driver named on the card, driver may rotate).**

Recommended modeling (only what the evidence supports):

| Entity | Justified? | Shape | Why |
|---|---|---|---|
| **`FuelCard`** | ✅ Yes | `card_number` (PK), `edk_account`, `status`, `active_from/to` | ~9 permanent physical cards; the stable identity every recharge/purchase hangs off. |
| **`FuelCardAssignment`** | ✅ Yes (kept minimal) | `fuel_card_id`, `truck_id`, `driver_id?`, `effective_from/to` | Cards are truck-anchored but reassignment happens; a temporal assignment gives correct historical attribution instead of trusting free-text `Porteur` per row. Start light (current assignment), extend to history only if reassignments prove frequent. |
| **`FuelCardRecharge`** | ✅ Yes = existing `edk_fuel_transactions` **re-scoped** | one row per recharge (R-2), FK→`FuelCard` | This is what the current table *actually* holds — a recharge, mislabeled as a "transaction/purchase". Keep the table; correct its meaning (do **not** treat `litres` as real). |
| **`FuelCardAccountMovement`** | ⚠️ Optional | R-1 rows (cash/transfer/adjustment) | Only if treasury reconciliation of the master account is a requirement. Currently **defer** — R-1 has no truck linkage and no operational use. |
| ~~`FuelCardTransaction` (purchase)~~ | ⛔ Conditional | per-fill at station | **Do not build now.** Requires the EDK purchase export. This is the future `FuelPurchase` (§5). |

**On `litres` in `edk_fuel_transactions`:** it is a *derived estimate* (`Montant ÷ price_per_litre`), computed at
import from one fleet-wide price. It must be **flagged as an estimate**, never surfaced as measured purchased
volume, and never fed into an efficiency KPI. (Carried over as Risk from the companion doc.)

---

## 5. Ownership Audit (Phase 5)

| Domain entity | Owner (source system) | Reason | Lifecycle | Build now? |
|---|---|---|---|---|
| **FuelRecharge** (`edk_fuel_transactions` re-scoped) | **EDK** | Money loaded onto a card | created at recharge; immutable | ✅ re-scope existing |
| **FuelCard / FuelCardAssignment** | **Internal ops config** | Human-maintained identity + assignment | permanent; assignment temporal | ✅ (small) |
| **FuelConsumptionDaily** (`fleeti_daily_records`) | **Fleeti — Volume 2.0 (consumption/refuel) + Carburant (tank/drain)** | Daily consumption truth | one row/(truck,date); upserted | ✅ exists |
| **FuelTelemetry** (`truck_telemetry_snapshots`/`fuel_trackings`) | **Fleeti GPS API (live)** | Sub-daily tank sensor | continuous | ✅ exists |
| **FuelEvent** (`fuel_events`) | **Internal detector over telemetry** | Refill/drop/theft facts | per event | ✅ exists |
| **FuelReconciliation** | **Internal domain (calculator)** | Cross-source comparison | derived on demand, **not persisted** | later (F6) |
| **FuelPurchase** | **EDK — _iff_ transaction export obtained** | Per-fill dispense at station | conditional/future | ⛔ blocked |
| **FuelStation** | **EDK — _iff_ transaction export obtained** | Merchant/station reference | conditional/future | ⛔ blocked |
| **FuelImportBatch** | Internal | Import audit/rollback | optional | defer |

**Net change vs the companion doc:** it labelled `edk_fuel_transactions` as `FuelCardRecharge` and rejected
`FuelPurchase`/`FuelStation` — **that ownership stands and is reinforced**. The only refinement: EDK's role is
now explicitly **conditional-upgradeable** (recharge today → purchase+station *if* the transaction export lands).

---

## 6. Relationship Between the Three (four) Sources (Phase 6)

```
EDK recharge  ──(money loaded onto card)──►  [ card float / budget ]
                                                     │  (NOT a per-fill link — no station, no litres, no timing match)
Fleeti Volume 2.0  ──(refuel litres + consumed + km, daily)──►  FuelConsumptionDaily  ◄── Fleeti Carburant (tank in/out, drains, daily)
Fleeti GPS API (live)  ──(tank sensor, sub-daily)──►  FuelTelemetry ──► FuelEvent (refill/drop/theft)
```

- **EDK owns _money recharged onto cards_** — a **financial/float** fact. It does **not** own "purchased fuel"
  (no litres, no station).
- **Volume de carburant 2.0 owns _consumed + refuelled litres + km_** (daily). ✅ the proposed model is correct.
- **Carburant owns _tank telemetry_** (opening/closing volume, drains) daily; the **live API is the authoritative
  real-time tank owner**, Carburant is its daily-aggregate/backfill twin.

So the intended "EDK owns Purchased, Volume owns Consumed, Carburant owns Tank" model is **half right**: Volume
and Carburant ownership hold; **EDK owns _Recharge_, not _Purchase_.** The purchase layer is a *gap*, fillable
only by the EDK transaction export.

---

## 7. Reconciliation Matrix (Phase 8→7)

Only what the **available** data supports. "Conditional" = unlocked by the EDK purchase export.

| Reconciliation | Supported now? | Basis / why not |
|---|---|---|
| Refueled vs Consumed | ✅ **Yes (strong)** | Both from Fleeti, same daily grain. |
| Purchased vs Tank | ⛔ **Conditional** | Needs per-fill purchase + tank; no purchase data now. |
| Purchased vs Consumed | ⛔ **No / Conditional** | No purchases. *Recharge≠purchase.* Only a coarse **money-recharged vs consumption-valued** float check is possible (relabel, see below). |
| Purchased vs Refueled | ⛔ **No / Conditional** | No purchase litres; Fleeti refills are daily aggregates → no per-event match. |
| Card vs Truck | ✅ **Yes** | Recharge → card → truck (registry, `Porteur` fallback). |
| Card vs Driver | ⚠️ **Weak** | Fuzzy `Porteur` name match only; advisory. |
| Station vs Consumption / Cost / Efficiency | ⛔ **No** | No station data anywhere. |
| Duplicate **recharges** | ✅ **Yes** | Unique `N transaction`; flag repeats. |
| Card misuse | ⚠️ **Partial** | Abnormal recharge frequency/amount, card↔truck mismatch. Not station-based. |
| Unauthorized station | ⛔ **No** | No station data. |
| Fuel theft / siphoning | ✅ **Yes (exists)** | Live telemetry `FuelEvent` DROP/THEFT — not EDK. |
| Sensor anomaly | ✅ **Yes** | Carburant min/max/avg + live sensor. |

**Relabel required (correctness):** `FuelComparisonService`'s "EDK litres purchased vs Fleeti litres consumed"
is **not** purchased-vs-consumed. It compares an *estimate of litres a recharge could buy* against *litres
consumed*. Present it as a **card-float / budget-vs-burn** indicator in FCFA, with a visible "estimated litres"
caveat — never as a purchase reconciliation.

---

## 8. Existing Code Consolidation Plan (Phase 8)

| Class / table | Disposition | Justification |
|---|---|---|
| `EdkFuelParser` | **Keep + split** | Must handle **both** EDK families (R-1 `compte`, R-2 `compte_carte`) and stop treating a recharge as a purchase; `litres` flagged estimate. |
| `edk_fuel_transactions` | **Re-scope → `FuelCardRecharge`** (add DB UNIQUE `(transaction_id, truck_id)`) | It stores recharges, not purchases; fixes the racy dedupe (companion P2). |
| `FleetiFuelParser` | **Upgrade** | Must read the two *new* Fleeti file formats (Volume 2.0 6-col, Carburant 12-col) — the current single-"Rapport" assumption reads neither (companion P3). |
| `fleeti_daily_records` | **Keep = canonical `FuelConsumptionDaily`** | Already the daily consumption truth; already upserts on `(truck,date)`. |
| `FuelComparisonService` | **Keep logic → move into `FuelReconciliationCalculator`; relabel output** | Reuse, don't duplicate; correct the "purchased" wording (§7). |
| `FuelTracking` / `FuelEvent` / detector | **Keep** | Live anomaly truth; not duplicated by Excel. |
| `truck_telemetry_snapshots` | **Keep (shared)** | Lossless telemetry owner. |
| `FuelCalculator` (`yieldPerTonne` only) | **Extend** (efficiency, per-tonne-km, spend, CO₂) | Single owner of fuel rules. |
| `FuelReadModel` | **Build (missing)** | `KpiDataSource::FUEL` resolves to `null` today — the R1.2 gap. |
| `daily_checklists` fuel fields | **Keep as human-observation input only** | Never authoritative volume. |
| New: `FuelCard` / `FuelCardAssignment` | **Create (small)** | Robust card→truck vs per-row regex. |
| ~~`FuelPurchase` / `FuelStation`~~ | **Do not create** | Blocked until EDK transaction export exists. |

---

## 9. Import Strategy (Phase 9)

**Sequence (unchanged and reinforced): Fleeti Volume 2.0 → Fleeti Carburant → EDK recharge.**

- **Consumption first** (Volume 2.0) establishes the operational spine — every reconciliation and KPI keys off
  it. **Telemetry second** (Carburant) enriches the same daily rows (tank/drains). **EDK last**, because a
  recharge only makes sense reconciled *against* an already-loaded consumption base (float/budget check).
- EDK is **not** promoted to "first/parallel" as an operational source, precisely because it is recharge, not
  purchase. **If** the EDK transaction export arrives, *that* file (purchases) would import parallel to Fleeti
  as a second operational feed — but consumption still loads first for the reconciliation base.
- Idempotency: Fleeti already upserts `(truck,date)`; EDK needs a real UNIQUE `(N transaction, truck)` + upsert.

---

## 10. Migration Impact (Phase 10)

**Does the roadmap change? Mostly no — because EDK is still not a purchase source with current data.** The
companion roadmap (F1–F10) holds, with these adjustments:

- **F2 grows in importance and scope:** relabel `edk_fuel_transactions` → `FuelCardRecharge`; **split the
  importer for the two EDK families** (R-1 account vs R-2 card); flag estimated litres; add the UNIQUE index.
- **F4 (FuelCard registry) is confirmed necessary** and slightly expanded (`FuelCardAssignment`).
- **New F2.5 — Obtain the EDK transaction/purchase export** (business action, blocking). Only after it lands:
  **new phase F11 — FuelPurchase + FuelStation + station reconciliations + purchased-vs-consumed KPI.**
- **Fuel KPIs are NOT redesigned around EDK now.** Core KPIs stay consumption/efficiency (Fleeti-owned);
  EDK-derived KPIs are **financial** (recharge spend, card float), explicitly labeled — not "fuel purchased".
- **Reconciliation does NOT move earlier.** It stays after Read Model + Calculator (frozen layer order).
- **Dashboards still wait** (F9). No dashboard is built on a misclassified source.

---

## 11. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Treating recharge as purchase → phantom "purchased litres" pollutes finance/efficiency KPIs | **High** | This ADR + F2 relabel; litres shown as estimate only; no purchase KPI until F11. |
| Accepting the new business framing without re-checking data → building `FuelStation`/`FuelPurchase` on absent data | **High** | Evidence-first: no station/litres column exists → both blocked (§3, §5). |
| Two EDK families silently mixed by the importer (R-1 account rows have no card/truck) | Medium | F2 split-by-header; route R-1 to (optional) account-movement, R-2 to card recharge. |
| Card→truck via free-text `Porteur` misattributes spend | Medium | `FuelCard`/`FuelCardAssignment` registry; regex only as fallback with a review queue. |
| Estimated litres from one fleet-wide price distort history | Medium | Store `price_per_litre` per recharge at import; price-by-period parameter (F7). |
| Waiting on the EDK transaction export blocks nothing else | Low | Roadmap already sequences purchase work as a separable F11; F1–F10 proceed regardless. |

## 12. Open Business Questions

1. **Does EDK expose a _transaction / consumption_ export (per fill: station, litres, amount, card, timestamp)?**
   This single answer decides whether `FuelPurchase` + `FuelStation` + station-level reconciliation ever exist.
   **#1 blocking action — request this export from the EDK portal.**
2. **`Mode de recharge` semantics** — is *"Transfert carte vers Compte"* money leaving a card (reducing its
   float) or an internal reclass? Needed to interpret R-1 correctly if we ever ingest it.
3. **Currency basis** — `Montant` is labelled FCFA yet `FleetSetting.price_per_litre` defaults to 730 in an
   MRU-priced context. Confirm the currency and the price used for any litre estimate.
4. **Card assignment source of truth** — is there a canonical card↔truck(↔driver) list to seed `FuelCard`
   /`FuelCardAssignment`, and do cards get reassigned over time?
5. **Recharge tiers** — are `210 000` / `238 000` official top-up amounts (per truck/route)? Confirms the
   "recharge, not purchase" reading and helps flag anomalous recharges.
6. **Is master-account treasury (R-1) in scope** for reconciliation, or only card-level operations (R-2)?

## 13. Final Recommendation

1. **Adopt the conditional classification:** EDK = **recharge / financial source today**; it is *not* an
   authoritative fuel-purchase source with the reports in hand. Do **not** build `FuelPurchase` or `FuelStation`
   now — there is no station or litre data to support them.
2. **Correct the existing model first (F2):** re-scope `edk_fuel_transactions` to **`FuelCardRecharge`**, split
   the importer for the two EDK families, flag estimated litres, add the UNIQUE dedupe index, and **relabel** the
   EDK-vs-Fleeti view as a **card-float / budget-vs-burn** indicator — not "purchased vs consumed".
3. **Obtain the EDK transaction export (F2.5, business action).** It is the *only* thing that upgrades EDK to the
   authoritative purchase source and unlocks station analytics (new F11). Everything else (F1, F3–F10) proceeds
   independently and is not blocked by it.
4. **Keep the ownership model:** Fleeti Volume 2.0 → consumption; Carburant/live → tank telemetry; EDK →
   recharge (→ purchase, conditionally); reconciliation derived, not persisted.

**No production code, migrations, importers, Read Models, Calculators, KPIs, dashboards, or DB changes until this
record and the companion architecture are reviewed and approved.**
