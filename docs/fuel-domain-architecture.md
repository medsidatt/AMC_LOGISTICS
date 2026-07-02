# Fuel Domain — Architecture Decision Document (DRAFT — awaiting approval)

> **Status: DRAFT for review.** No production code, migration, importer, Read Model, Calculator,
> KPI, or React page is created by this document. Implementation begins only after approval.
> Governed by the [Operational Intelligence Platform architecture (FROZEN)](operational-intelligence-architecture.md)
> and the [Platform Design Constitution](workspace-standard.md).
> Date: 2026-07-01. Author: Fuel Domain audit.

---

## F1 — Consolidation delivered (2026-07-01)

Phase F1 (foundation) is **implemented**. Canonical source roles are now enforced in code:

| Role | Source | Owns |
|---|---|---|
| **Operational** | Fleeti **Volume de carburant 2.0** | daily consumption · refuel litres · km (`fleeti_daily_records`) |
| **Telemetry** | Fleeti **Carburant** | tank opening/closing · drains (same daily rows) + live GPS sensor (`fuel_trackings`/`fuel_events`) |
| **Financial** | **EDK Recharge** | card money top-ups; `estimated_litres` is a flagged estimate (`edk_fuel_recharges`) |
| **Future (BLOCKED)** | **EDK Fuel Purchase Export** | per-fill purchases + station — deferred until AMC provides the export |

**FuelPurchase and FuelStation remain intentionally deferred**: the available EDK exports are recharge
ledgers with no litres/station data, so building those entities would be speculative (see
[`fuel-edk-reclassification.md`](fuel-edk-reclassification.md)). What F1 shipped: `EdkFuelTransaction` →
`EdkFuelRecharge` (table `edk_fuel_recharges`, `litres` → `estimated_litres`, UNIQUE dedupe + upsert), a
layout-aware `FleetiFuelParser` that ingests **both** current Fleeti exports (and legacy) with source-owned
partial columns, and consistent "recharge / estimated / budget" terminology across API and UI. Not built (later
phases): FuelReadModel, FuelCalculator methods, KPIs, reconciliation engine, dashboard.

## 0. Executive summary (read this first)

The task framed Fuel as a greenfield bounded context with three new sources. **It is not greenfield.**
A substantial Fuel implementation already exists, across **two independent pipelines** and **six tables**.
The real work is **consolidation and correction**, not construction. The three headline decisions:

1. **EDK is not a fuel-purchase source _with the reports we hold_. It is a fuel-card _recharge_ (top-up) ledger.**
   Re-audited in depth — see **[`fuel-edk-reclassification.md`](fuel-edk-reclassification.md)** (authoritative on
   EDK). There are **two** EDK recharge families (account-level `compte`; card-level `compte_carte`), amounts
   cluster on fixed top-up tiers (210 000 ×156 / 238 000 ×47 = 93% of 219 rows), and **no litres, station, pump,
   or odometer** appears in any file. "Litres purchased" is a *derived estimate* only. **Conditional upgrade:**
   EDK becomes the authoritative purchase source (+`FuelStation`) *iff* AMC exports EDK's per-fill
   transaction/consumption report — that export is the #1 blocking business action.

2. **The two Fleeti files are the same telemetry, split into two exports** — and the existing importer
   (`FleetiFuelParser`) only recognizes the **retired single "Rapport de carburant" format**. It will parse
   **neither** new June file as-is. `Volume de carburant 2.0` = refuel/consumption view (6 columns);
   `Carburant` = tank-telemetry view (12 columns) + tank statistics. Both are **daily-per-vehicle aggregates**,
   not per-refuel events — this is the single most important granularity fact in the whole domain.

3. **`FleetiDailyRecord` already is the canonical daily consumption table** and already merges *both* Fleeti
   views (refills, consumed, km **and** volume_initial/final, drains). The canonical operational entity we would
   "design" already exists. The live sensor pipeline (`FuelTracking` → `FuelEvent` → `TheftIncident`) is a
   *different* truth (real-time anomaly), not a duplicate. The domain's problem is **role ambiguity between
   these tables**, already flagged in `docs/audit/05-architecture.md`.

**Recommendation:** do **not** add new entities first. Ratify ownership of the tables that exist, fix the EDK
semantic error, upgrade the importer to the split file formats, add the missing `FuelReadModel` +
`FuelCalculator` methods + one Fuel KPI set, and only then build a Fuel command center. Full roadmap in §10.

---

## 1. Repository Audit

### 1.1 What already exists (inventory)

**Two pipelines feed the Fuel domain:**

**Pipeline A — Live telemetry (real-time, Fleeti GPS API):**
`FleetiSyncService::syncKilometers()/syncLive()` →
`TelemetrySnapshotService` (`TruckTelemetrySnapshot`, lossless) →
`FuelTrackingService` (`FuelTracking`, per-snapshot tank level enriched with GPS/ignition) →
`FuelEventDetectorService` (`FuelEvent`: REFILL / DROP / THEFT_SUSPECTED) →
`TheftIncidentService` (`TheftIncident`) + `DailyDispatchEventDeriver` (`DailyDispatchEvent` REFUEL / FUEL_LOSS).
Consumed by `TruckKpiService`, `DriverKpiService` (anomaly counts), and the live dispatch timeline.

**Pipeline B — Batch import (Excel/CSV upload, manual):**
`FuelImportController` →
`FleetiFuelParser` (Excel → `FleetiDailyRecord`, daily per-vehicle) +
`EdkFuelParser` (CSV → `EdkFuelTransaction`, card recharges, money→litres) →
`FuelComparisonService::forTruck()` (monthly EDK-litres vs Fleeti-consumed reconciliation).

**Domain / Operational-Intelligence layer (partial):**
- `Domain/Operations/Calculations/FuelCalculator.php` — **only** `yieldPerTonne(litres,tonnage)`.
- `Domain/Operations/Contracts/FuelCalculatorInterface.php`.
- `Domain/Operations/Events/FuelConsumptionAbnormal.php` — business event VO (unconsumed, per R1.4);
  `EventId::FUEL_CONSUMPTION_ABNORMAL` registered.
- `tests/Feature/Operations/FuelCalculatorTest.php` (yield formula + zero-tonnage guard).
- **No** `FuelReadModel`: `KpiDataSource::FUEL` **exists as an enum case but `contract()` returns `null`** — the
  interface is unbuilt (R1.2 gap). Fuel KPI units (`litres`, `litres-per-tonne`) are reserved/deferred in
  `KpiUnit` / Analytics `MetricUnit`. **No** Fuel KPI in `KpiRegistry`, **no** Fuel Translator / command center.

**Config / thresholds (existing):** `FleetSetting.price_per_litre` default **730** (used by EDK money→litres);
`maintenance.fuel_refill_threshold_litres` (30), `maintenance.fuel_drop_threshold_litres` (15); theft severity
HIGH when drop ≥ 50 L. Column is named `amount_fcfa` yet the default price sits in a MRU-priced settings row —
**currency basis must be confirmed** (Open Question §11.1).

**Models (6):** `FuelTracking`, `FuelEvent`, `EdkFuelTransaction`, `FleetiDailyRecord`,
`TruckTelemetrySnapshot` (shared), `TheftIncident` (shared). Plus fuel columns on `Truck`
(`fleeti_asset_id`, `fleeti_gateway_id`, `fleeti_last_fuel_level`).

**A 4th (minor) fuel input exists:** `daily_checklists.fuel_level` (text), `.fuel_refill` (bool),
`.fuel_filled` (decimal) — driver-observed fuel. `FuelTracking.source` is an enum `fleeti | checklist | manual`,
so the checklist can feed `FuelTracking`. This is a *human observation* source, not a measurement feed; it must
not be treated as authoritative volume (ownership in §4 keeps Fleeti/live authoritative).

**Existing analytics surfaces (avoid duplication):** `resources/js/pages/analytics/Fleeti.tsx` (a Fleeti fuel
report page) already exists, and `Dashboard.tsx` / `DriverDashboard.tsx` / `TruckKpiSection` / `DriverKpiSection`
already render fuel metrics. Per the Constitution's "never create duplicate dashboards/KPIs", the Fuel command
center (F9) must **extend/replace** these, not add a parallel one.

**Migrations:** `2026_04_07_050000_add_fuel_fields_to_trucks_table`,
`2026_04_07_060000_create_fuel_trackings_table`, `2026_04_09_120000_enrich_fuel_trackings_table`,
`2026_04_09_140000_create_fuel_events_table`, `2026_05_14_110000_create_edk_fuel_transactions_table`,
`2026_05_14_110100_create_fleeti_daily_records_table`.

**Routes (`routes/web.php`, prefix `fuel`):** `fuel.index`, `fuel.export`, `fuel.edk.show`,
`fuel.fleeti.show`, `fuel.import` (legacy redirect), `fuel.import.edk.preview/commit`,
`fuel.import.fleeti.preview/commit`.

**React (`resources/js/pages/fuel/`):** `Index.tsx` (workspace) + `components/`
`FuelImportDrawer`, `FuelDetailsDrawer`, `FuelFilters`; plus `components/truck/FuelComparisonSection.tsx`
(per-truck EDK-vs-Fleeti table). No fuel dashboard / analytics page.

**Confirmed physical schema (key columns):**
- `fleeti_daily_records`: `truck_id`, `record_date`, `kilometers`, `volume_initial`, `volume_final`, `consumed`,
  `consumed_per_100km`, `refills_count`, `refills_volume`, `drains_count`, `drains_volume`, `imported_by`;
  **UNIQUE (`truck_id`,`record_date`)** + index on `record_date`.
- `edk_fuel_transactions`: `truck_id`, `driver_id?`, `transaction_id?` (indexed, **not unique**), `card_number?`,
  `holder_raw?`, `amount_fcfa`, `litres`, `price_per_litre`, `occurred_at`, `imported_by`; indexes on
  `transaction_id`, `occurred_at`, (`truck_id`,`occurred_at`).
- `fuel_trackings`: `truck_id`, `litres`, `kilometers_at?`, `engine_hours_at?`, `latitude?`, `longitude?`,
  `ignition_on?`, `source` (`fleeti|checklist|manual`), `telemetry_snapshot_id?`; index (`truck_id`,`created_at`).
- `fuel_events`: `truck_id`, `event_type` (`refill|drop|theft_suspected`), `litres_delta`, `litres_before/after`,
  `odometer_km?`, GPS, `ignition_on?`, `detected_at`, `snapshot_before_id?`, `snapshot_after_id?`,
  `reviewed_at?`, `reviewed_by?`, `notes?`.
- `truck_telemetry_snapshots`: `fuel_litres?` + full telemetry + `raw_payload` (json, lossless).

### 1.2 Problems found (existing debt — several pre-documented in `docs/audit/`)

| # | Problem | Evidence |
|---|---------|----------|
| P1 | **EDK litres = single current price × all historical rows** — historical litres wrong when price changes. | `EdkFuelParser::parse($contents, $pricePerLitre)`; `docs/audit/04-business-logic.md #10` |
| P2 | **EDK import: no unique constraint + racy SELECT-then-INSERT dedupe** on *financial* data (double-import inflates spend). | `docs/audit/04-business-logic.md #3`; `FuelImportController` |
| P3 | **Importer targets the retired single-file format.** `FleetiFuelParser` matches header `"Rapport de carburant"`; both June files use `"Volume de carburant 2.0"` / `"Carburant:"`. Neither new file imports. | `FleetiFuelParser.php:67`; file headers (§2) |
| P4 | **Role ambiguity across 4 fuel models** — no documented owner/boundary. | `docs/audit/05-architecture.md` ("document the intended role of each fuel model or merge") |
| P5 | **Synchronous parse/commit**, `memory_limit=1024M`, `set_time_limit(180)`, per-row `exists()` dup check. | `docs/audit/11-performance.md #5` |
| P6 | **`fuel/Import` uses native `confirm()`/`alert()`**; component ~490 LOC. | `docs/audit/02-ui-ux.md`, `10-code-quality.md` |
| P7 | **Single global fuel price in `FleetSetting`** (not per-period, not per-tenant). | `docs/audit/08-saas-readiness.md` |
| P8 | **No `FuelReadModel`, no Fuel KPI registry entry** — Fuel bypasses the frozen layered architecture. | `operational-intelligence-architecture.md` §5 R1.2 |
| P9 | **No CO₂ / fuel-per-tonne-km KPI** despite having litres + km + tonnage. | `docs/audit/09-iso-compliance.md` |

### 1.3 Dead / near-dead code
- `FuelComparisonService` references `FleetiDailyRecord` (Pipeline B) — **healthy**.
- `FuelTracking` / `FuelEvent` are **live** (still written by sync, read by KPI services) — **not dead**, but
  **overlap** the Carburant Excel daily tank data. This overlap is the core consolidation question (§4, §6).
- No orphaned fuel controllers/routes detected; `public/uploads/*.xlsx` are import artifacts (not tracked schema).

### 1.4 Risk assessment: **MEDIUM–HIGH**
Financial data (EDK spend) with a known dedupe race and a semantic error (money treated as litres), plus an
importer that silently won't ingest the current source files. Correctness-first work, behind characterization tests.

---

## 2. Source Analysis

### Source 1 — `Volume_de_carburant_2.0-20260629-1233.xlsx` (Fleeti)

| Attribute | Finding |
|---|---|
| **Business purpose** | Operational **refuel + consumption** view per vehicle. |
| **Business owner** | Fleet / Logistics Manager (operational). |
| **Structure** | Multi-sheet workbook: `Résumé` (per-vehicle totals) + 2 sheets per truck: header + `Détail par dates`. |
| **Granularity** | **Vehicle × day.** Refills given as *daily count + daily total litres*, **not** per-refuel events. |
| **Detail columns** | `Date`, `kilometrage km`, `Nombre de Ravitaillements`, `Volume L`, `Consommé L`, `Consommation L/100km`. |
| **Primary key** | (`truck`, `date`). |
| **Update frequency** | Manual export (period-based; June file covers "1 juin →…"). |
| **Strengths** | Clean daily consumption + km + refuel litres → the operational efficiency spine. |
| **Weaknesses** | No tank level, no drains, no per-transaction timing, no cost, no driver. |
| **Missing** | Station, pump, driver, intra-day timing. |
| **Data quality** | Good; accented headers (`Consommé`, `kilométrage`) need encoding-safe parsing. |
| **Future suitability** | **Canonical source for daily consumption & refuel litres.** |

### Source 2 — `Carburant-20260629-1235.xlsx` (Fleeti)

| Attribute | Finding |
|---|---|
| **Business purpose** | **Tank telemetry** view: sensor levels, refills, **drains (vidages)**, tank statistics. |
| **Business owner** | Fleet (anomaly / theft investigation). |
| **Structure** | `Résumé` + **3 sheets per truck**: header, `Détail par dates` (12 cols), `Données statistiques` (tank min/max/avg per day). |
| **Granularity** | **Vehicle × day** (tank aggregates), plus daily tank Min/Max/Avg. |
| **Detail columns** | `Date`, `kilometrage km`, `Consommation par calcul` (×2, mostly `—`), `Volume initial L`, `Volume final L`, `Consommé L`, `Consommation L/100km`, `Remplissages Nombre/Volume`, `Vidages Nombre/Volume`. |
| **Stats columns** | `Date`, `Minimum L`, `Maximum L`, `Valeur moyenne L`. |
| **Primary key** | (`truck`, `date`). |
| **Strengths** | Tank opening/closing balance + drains → enables **tank-variation** & drain/siphoning signals offline. |
| **Weaknesses** | Same sensor as the live API (potential double source of truth); "Consommation par calcul" columns largely empty. |
| **Missing** | Per-event timing, station, driver, cost. |
| **Data quality** | Good; GPS-vs-sensor estimation exists in the *live API* payload, not fully in this export. |
| **Future suitability** | **Backfill / audit source for tank variation & drains.** Live sensor stays authoritative for real-time. |

### Source 3 — `histo_rechaerge_compte_carte20260701_130859.csv` (EDK)

| Attribute | Finding |
|---|---|
| **Business purpose** | **Fuel-card account _recharge_ (money top-up) ledger.** Filename = *histo recharge compte carte*. |
| **Business owner** | Finance. |
| **Granularity** | **Card × recharge event** (timestamped to the second). 74 rows, 8 cards, ~7 trucks, spanning ~2 days. |
| **Columns** | `ID Transaction` (**always `0` — useless**), `N transaction` (**the real unique key**), `Date` (`01-Juil-2026 11:47:26`), `Montant` (FCFA), `Numero carte`, `Porteur` (embeds **plate + driver name**, free-text). |
| **Primary key** | `N transaction` (unique 74/74). Store as `transaction_id`; **do not** use col0. |
| **Amounts** | Vary: 210 000 ×69, 111 000, 74 470, 44 000, 40 620 ×2 → **top-ups, not per-litre dispenses.** |
| **Strengths** | Authoritative **money on each card**, per card, timestamped → spend/budget, card misuse, duplicate-recharge. |
| **Weaknesses** | **No litres, no station, no odometer.** Recharge ≠ dispense (money loaded today, fuel burned later). Card→truck only via fuzzy plate-in-`Porteur`. |
| **Missing** | Litres, price, station, pump, dispense timestamp, structured card↔truck link. |
| **Data quality** | Medium: free-text `Porteur`, French month spellings incl. truncations (`Jui`/`Juil`), accented. |
| **Future suitability** | **Financial source only.** Litres is a *derived estimate*, never authoritative volume. |

---

## 3. Canonical Domain Model

Design principle: **reuse the tables that exist; give each one ONE owner and ONE responsibility.** Only one new
persisted concept is genuinely justified (the card registry). Names below are *domain concepts*; the physical
tables they map to are in the Ownership Matrix (§4).

| Domain entity | Justified? | Physical table (existing) | Grain | Purpose |
|---|---|---|---|---|
| **FuelConsumptionDaily** | ✅ exists | `fleeti_daily_records` | vehicle×day | Canonical daily consumption, km, refuel litres, tank balance, drains. **The One Truth for "how much fuel a truck used."** |
| **FuelTelemetrySnapshot** | ✅ exists | `truck_telemetry_snapshots` + `fuel_trackings` | per GPS ping | Real-time tank level / GPS / ignition. Source for live anomaly. |
| **FuelEvent** | ✅ exists | `fuel_events` | per event | REFILL / DROP / THEFT_SUSPECTED derived from telemetry. |
| **FuelCardRecharge** | ✅ exists (mis-named) | `edk_fuel_transactions` | per recharge | Money loaded on a card. *Rename semantics; keep table.* Litres = derived estimate, flagged as such. |
| **FuelCard** | ✅ **new (small)** | *(new)* `fuel_cards` | per card | `card_number → truck_id / driver_id`, active-from/to. Replaces per-import regex guessing (§5). |
| **FuelReconciliation** | ⚠️ derived, **not persisted** | *(none in R1)* | vehicle×period | Computed on demand by a calculator (mirrors R1.4 "events derived, not persisted"). |
| ~~FuelPurchase~~ | ❌ reject | — | — | No per-litre purchase data exists in any source. Do not invent it. |
| ~~FuelStation~~ | ❌ reject | — | — | No station data in any source. |
| ~~FuelImportBatch~~ | ⛔ defer | — | — | Only if import history/audit becomes a requirement (see §9 Open Questions). |

**Why no `FuelPurchase` / `FuelStation`:** honoring the Constitution's "reuse before create / justify every field",
neither is supported by the available data. Creating them would be speculative schema.

---

## 4. Ownership Matrix

| Entity / table | **Owning source** | Reason |
|---|---|---|
| `fleeti_daily_records` (FuelConsumptionDaily) | **Fleeti — `Volume de carburant 2.0` (consumption/refuel cols) + `Carburant` (tank/drain cols)** | Both Fleeti exports write the same daily row; Volume 2.0 owns consumption/refuel fields, Carburant owns `volume_initial/final` + drains. **One row per (truck,date); the two files fill complementary columns.** |
| `truck_telemetry_snapshots` / `fuel_trackings` | **Fleeti GPS API (live)** | Only the live API produces sub-daily sensor readings. The `Carburant` Excel is a **daily rollup of the same sensor** — used for backfill/audit, never overwriting live truth. |
| `fuel_events` | **Internal (FuelEventDetectorService over live telemetry)** | Derived anomaly facts. |
| `edk_fuel_transactions` (FuelCardRecharge) | **EDK** | Financial transactions. |
| `fuel_cards` *(new)* | **Internal (operations config)** | Human-maintained mapping; not owned by any feed. |
| FuelReconciliation | **Internal domain (calculator)** | Computed by comparing owners above. |

**Conflict rule (tank telemetry):** live API is authoritative for real-time tank state; `Carburant` Excel may
**backfill days the live feed missed** but must never overwrite an existing live-sourced value. Every daily row
carries `source` provenance so the two never silently collide.

---

## 5. Entity Relationships & Matching Strategy

```
Truck (hub: id, matricule "6078TTA1", fleeti_asset_id)
 ├─1:N─ FleetiDailyRecord      (truck_id, record_date)          ← Fleeti Volume2.0 + Carburant
 ├─1:N─ TruckTelemetrySnapshot (truck_id, recorded_at) ─1:1─ FuelTracking
 ├─1:N─ FuelEvent              (truck_id, occurred_at)          ← detector
 ├─1:N─ EdkFuelTransaction     (truck_id, occurred_at, card)    ← EDK recharge
 └─1:N─ FuelCard (new)         (card_number → truck_id/driver_id, active window)
Driver ─1:N─ FuelCard ;  Driver ~ EdkFuelTransaction (fuzzy, via Porteur)
```

**Matching rules (only what the data supports):**

| Match | Key | Confidence | Notes |
|---|---|---|---|
| Vehicle identity (all sources) | normalized `matricule` `NNNN`+`TTA1` | High | Already implemented in both parsers + `FleetiService::normalizeMatricule`. Fleeti also carries `fleeti_asset_id`. |
| **Card → Truck** | `fuel_cards.card_number` lookup; **fallback** regex plate-in-`Porteur` | High with registry / Medium with regex | Recommend the persistent `fuel_cards` registry; current per-import regex is fragile (`docs/audit`). |
| Recharge → Driver | fuzzy name tokens in `Porteur` (≥2 tokens) | Low–Medium | Keep as advisory only; never a financial key. |
| **EDK recharge ↔ Fleeti refuel (per event)** | — | **IMPOSSIBLE** | EDK = money top-up events; Fleeti refills = *daily aggregated litres*. No shared transaction/pump/timestamp. |
| EDK spend ↔ Fleeti consumption | (`truck`, **month**) | Medium | The only defensible EDK↔Fleeti join — **money budget vs consumption**, month grain (as `FuelComparisonService` already does). |
| Live telemetry ↔ Carburant Excel | (`truck`, `date`) | High | Same sensor; join to validate/backfill, with `source` provenance. |

**Explicit non-capabilities:** there is **no** basis to match an individual card recharge to an individual
refuel, to attribute fuel to a station, or to compute per-transaction litres purchased. Any KPI implying these
is blocked (§8).

---

## 6. Reconciliation Strategy

Only reconciliations the data genuinely supports:

| Reconciliation | Supported? | Sources | Grain | Meaning / limit |
|---|---|---|---|---|
| **Refueled vs Consumed** | ✅ **Strong** | Fleeti Volume2.0 (refills) vs Fleeti (consumed) | truck×period | Both from one feed, same grain. Best-quality check. |
| **Tank variation** (`Vinit − Vfinal ≈ Consumed + Drains − Refills`) | ✅ **Strong** | Fleeti `Carburant` | truck×day | Internal consistency of the tank ledger; flags sensor/drain anomalies. |
| **Purchased(money) vs Consumed(value)** | ✅ **Medium** | EDK `Montant` vs Fleeti `Consommé × price` | truck×month | **Financial budget reconciliation.** Timing caveat: recharge ≠ dispense. |
| **Fuel drop / siphoning (real-time)** | ✅ **Strong (exists)** | Live telemetry → `FuelEvent` DROP/THEFT | per event | Already implemented; the authoritative theft signal. |
| **Drain (vidage) audit** | ✅ **Medium** | Fleeti `Carburant` drains | truck×day | Offline corroboration of live DROP events. |
| **Sensor anomaly** | ✅ **Medium** | `Carburant` min/max/avg vs consumed; live GPS-vs-sensor | truck×day | Implausible tank swings. |
| **Card misuse / duplicate recharge** | ✅ **Medium** | EDK | card | Duplicate `N transaction`, abnormal recharge frequency/amount, card↔truck mismatch. |
| **Purchased-litres vs Refueled-litres** | ❌ **Not supported** | — | — | EDK has no litres; converting money→litres then comparing to Fleeti litres compounds two estimates. Report money side only. |
| **Station anomaly** | ❌ **Not supported** | — | — | No station data anywhere. |
| **Delayed synchronization** | ⚠️ Partial | Live feed gaps | — | Detectable as missing snapshot days, not as a fuel reconciliation per se. |

**`FuelReconciliation` is computed on demand by a `FuelReconciliationCalculator`, not persisted** (mirrors the
frozen R1.4 "events derived, not persisted" rule). It consumes the `FuelReadModel`, never queries Eloquent directly.

---

## 7. Future KPI Readiness

Each KPI is placed against the frozen layer stack (ReadModel → Parameter → Calculator → KPI Registry).
"Ready" = all inputs exist today; "Blocked" = named blocker.

| KPI | Read Model | Calculator | Parameters | Data | Status |
|---|---|---|---|---|---|
| **Consumption L/100km** (truck/fleet) | FuelReadModel *(build)* | FuelCalculator.efficiency *(add)* | none | Fleeti daily | **Ready** (data exists; needs ReadModel+method) |
| **Fuel yield L/tonne** | FuelReadModel + transport tonnage | `FuelCalculator::yieldPerTonne` *(exists)* | none | Fleeti + TransportTracking | **Ready** |
| **Fuel-per-tonne-km** | FuelReadModel + tonnage + km | FuelCalculator *(add)* | none | Fleeti | **Ready** |
| **CO₂ estimate** | FuelReadModel | FuelCalculator.co2 *(add)* | `co2_emission_factor` *(new param)* | Fleeti litres | **Ready** once param seeded |
| **Refueled-vs-Consumed gap** | FuelReadModel | FuelReconciliationCalculator *(add)* | tolerance % *(new param)* | Fleeti | **Ready** |
| **Tank-variation anomaly** | FuelReadModel | FuelReconciliationCalculator | anomaly threshold *(param)* | Fleeti Carburant | **Ready** |
| **Monthly fuel spend / spend-per-km** | FuelReadModel (EDK side) | FuelCalculator.spend *(add)* | none | EDK | **Ready** (money only) |
| **EDK-vs-Fleeti financial gap %** | FuelReadModel | FuelReconciliationCalculator | gap threshold *(param)* | EDK + Fleeti | **Ready** (logic exists in `FuelComparisonService`; move into calculator) |
| **Fuel-theft / siphoning rate** | FuelReadModel (events) | (existing detector) | drop thresholds *(params)* | Live telemetry | **Ready** (exists; formalize into KPI) |
| **Card-misuse incidents** | FuelReadModel (EDK) | FuelCalculator.cardAnomaly *(add)* | freq/amount bounds *(param)* | EDK | **Ready (medium confidence)** |
| **Litres purchased per truck** | — | — | — | — | **Blocked** — EDK has no litres (only money). |
| **Cost per litre by station** | — | — | — | — | **Blocked** — no station/pump/litre data. |
| **Pump-level fraud** | — | — | — | — | **Blocked** — no dispense records. |

**Anti-duplication:** "Fuel per rotation" is already retired (folded into KPI-FLT-203, `docs/kpi-catalog.md:487`).
Do **not** reintroduce it. Fuel efficiency currently duplicated ×4 across KPI services → the calculator becomes
the single owner (per frozen §L2 "fuel-yield ×4").

---

## 8. Import Architecture

**No implementation here — recommendations only.**

- **Import order (per period):** ① Fleeti `Volume de carburant 2.0` → ② Fleeti `Carburant` (fills tank/drain
  columns on the same daily rows) → ③ EDK recharge CSV. Consumption spine first, telemetry enrichment second,
  finance last (finance reconciles against an already-loaded consumption base).
- **Format upgrade (required):** teach `FleetiFuelParser` the **two new headers/layouts** (`Volume de carburant 2.0`
  6-col sheet; `Carburant` 12-col sheet + stats sheet) and retire the single "Rapport de carburant" assumption.
  Detail sheets are suffixed ` - 2` / ` - 3`; parse by header text, not sheet index.
- **Frequency:** manual, period-batched (matches how the files are exported today). Live telemetry stays
  continuous via the existing scheduler.
- **Idempotency / duplicate detection:**
  - Fleeti daily: **already upserts on the existing UNIQUE (`truck_id`, `record_date`)** — keep; Carburant just
    enriches the same key (fill tank/drain columns).
  - EDK: `transaction_id` is currently **indexed but not UNIQUE**, and dedupe is an app-level per-row `exists()`
    (racy — P2). Fix: **add DB UNIQUE (`transaction_id`=`N transaction`, `truck_id`)** + `upsert`. Never key on
    col0 (`ID Transaction` = 0).
- **Error handling:** the current preview→commit two-step is good UX; keep it, but replace native
  `alert()/confirm()` (P6) and move heavy parse off the request (queue/job) to drop the 1024M/180s limits (P5).
- **Batch strategy / audit trail:** record `imported_by` + `source_file` + period on each row (already partly
  present via `imported_by`). A dedicated `fuel_import_batches` table is **optional** (§9) — only if import
  history/rollback is a real requirement.
- **Provenance:** every daily row stores `source` (`fleeti_volume` / `fleeti_carburant` / `live`) so the
  telemetry-overlap conflict rule (§4) is enforceable.

---

## 9. Migration Roadmap

Sequenced to fix correctness before adding surface, and to conform to the frozen layer order. Each step is
independently shippable behind characterization tests.

| Phase | Deliverable | Notes |
|---|---|---|
| **F1** | **This ADD approved** + ownership/roles for the 4 fuel tables ratified (closes `docs/audit/05` P4). | Doc only. |
| **F2** | **EDK semantic fix**: treat as `FuelCardRecharge`; litres = flagged estimate; **unique index + upsert** (P1, P2). | Correctness + finance safety. |
| **F3** | **Importer upgrade** to the split Fleeti formats (P3); upsert on (truck,date); `source` provenance. | Unblocks ingesting current files. |
| **F4** | **`FuelCard` registry** (card_number→truck/driver) + fallback regex. | Robust card→truck. |
| **F5** | **`FuelReadModel`** (+ `FuelReadModelInterface`) — the only Fuel DB reader (P8, closes R1.2 gap). | Frozen L0. |
| **F6** | **`FuelCalculator` methods** (efficiency, per-tonne-km, spend, CO₂) + **`FuelReconciliationCalculator`**; migrate the ×4 duplicated fuel-yield out of KPI services; move `FuelComparisonService` logic into the calculator. | Frozen L2. |
| **F7** | **Fuel parameters** seeded (price history, tolerance %, CO₂ factor, anomaly thresholds) via `OperationalParameterService`. | Frozen L1; fixes P7 direction. |
| **F8** | **Fuel KPI registry** entries (one definition each) + **FuelConsumptionAbnormal** deriver wired. | Frozen L4/L3. |
| **F9** | **Fuel Intelligence + Translator + command center** (exceptions-first: reconciliation gaps, theft, card misuse) — **then** the dashboard-migration continuation the task set out to unblock. | Frozen L5–L7. |
| **F10** | Async import (queue) + native-dialog removal (P5, P6); optional `fuel_import_batches`. | Perf/UX cleanup. |

This reorders the task's example roadmap (M1…M12): its M2 "new canonical DB" and M3–M5 "build imports" are
**largely already built** — replaced here by *correct/upgrade* (F2–F4). Its M6–M12 map onto F5–F9.

---

## 10. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| EDK money treated as litres propagates into finance KPIs | High | F2: relabel as estimate; report money as primary; document the caveat on every purchased-vs-consumed view. |
| Importer silently ingests 0 rows from new files (looks "successful") | High | F3: add format assertions + row-count guardrails in preview; fail loudly on unknown header. |
| Live-telemetry vs Carburant-Excel double source of truth for tank state | Medium | §4 conflict rule + `source` provenance; live authoritative, Excel backfill-only. |
| Consolidating 4 fuel models breaks live KPI/anomaly reads | Medium | Characterization tests before any move (frozen R1.8 discipline); no deletes until parity proven. |
| Card→truck via free-text `Porteur` misattributes spend | Medium | F4 `FuelCard` registry; regex only as fallback with a "needs review" queue. |
| Price-history absence makes historical litres non-reproducible | Medium | F7 price-by-period parameter; store `price_per_litre` on each recharge at import time (already a column). |
| Scope creep into a "fuel dashboard" before correctness fixed | Medium | Roadmap forces F2–F8 before F9. |

## 11. Open Business Questions

1. **Currency:** EDK `Montant` — FCFA/XOF (Senegal purchase) vs the MRU price in `FleetSetting`? The corridor is
   Senegal→Mauritania; confirm the currency and the price basis used to estimate litres.
2. **Price source of truth:** is there an official price-per-period (per country/station), or is the single
   `FleetSetting` value the only reference? Needed for F7 and defensible historical litres.
3. **Card ↔ truck assignment:** is there a canonical card assignment list (to seed `fuel_cards`), or must it be
   inferred from `Porteur`? Are cards ever reassigned between trucks/drivers over time?
4. **Recharge vs dispense:** does EDK ever provide *dispense/pump* data elsewhere (litres at pump), or is recharge
   the only signal? This decides whether "purchased litres" can ever be more than an estimate.
5. **Telemetry ownership:** should the `Carburant` Excel ever backfill tank telemetry, or is the live API the
   sole tank-state authority (making Carburant purely an audit cross-check)?
6. **Import history:** is a persisted `fuel_import_batches` audit/rollback trail required, or is per-row
   `imported_by` sufficient?
7. **Drains (vidages):** are drains operational (authorized tank service) or anomalies to alert on? Changes
   whether drain volume feeds theft signals or is netted out of consumption.

## 12. Final Recommendation

**Approve a consolidation-first Fuel architecture, not a greenfield build.**

- **Ratify** the six existing tables with the ownership/roles in §4; add exactly **one** new persisted entity
  (`fuel_cards`) and **one** derived-only concept (`FuelReconciliation`). Reject `FuelPurchase` and `FuelStation`
  as unsupported by the data.
- **Fix first** the two correctness defects that make current fuel numbers untrustworthy: EDK money-as-litres
  (F2) and the importer's inability to read the current source files (F3).
- **Then conform** Fuel to the frozen Operational-Intelligence stack (FuelReadModel → FuelCalculator methods →
  parameters → KPI registry → intelligence → command center), reusing `FuelComparisonService` logic rather than
  re-implementing it.
- **EDK is finance, Fleeti is operations, live telemetry is anomaly** — three non-duplicate roles. The only
  robust cross-source reconciliations are Refueled-vs-Consumed, Tank-variation, and money-based
  Purchased-vs-Consumed (monthly). Everything implying per-litre purchase, station, or pump is **blocked** and
  must not be built.

**No production code, migrations, importers, Read Models, Calculators, KPIs, or React until this document is
reviewed and approved.**
