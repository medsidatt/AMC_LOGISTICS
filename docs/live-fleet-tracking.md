# Live Fleet Tracking — System Reference

Status as of 2026-05-21. Captures the live tracker built around the
Logistics Responsible's daily WhatsApp dispatch program, and the
in-progress Pusher/WebSocket layer.

## Why this exists

The Logistics Responsible publishes a daily dispatch list (which driver/truck
runs today) and sends it to the WhatsApp group. Trucks haul basalt from
Senegal quarries (CSE, COGECA near Thiès) to Rosso/Mauritania. Pain point:
when Fleeti's dashboard shows trucks moving, our platform was showing them
parked — because `fleeti:sync-kilometers` only ran every 30 minutes,
fleet-wide. Combined with the under-ticketing gap (March 2026: 34 GPS CSE
visit-days vs 13 CSE tickets), the system had no live view of dispatch
execution.

---

## Architecture (current)

```
Fleeti API
   ▲
   │ HTTP polling (no webhooks available — checked storage/fleeti-swagger.json)
   │
   ├── fleeti:sync-kilometers          ── 30 min  · full fleet · DEEP  (odometer + engine hours)
   ├── fleeti:sync-fleet-positions     ── 2 min   · full fleet · LIGHT (bulk /Asset/Search only)
   └── fleeti:sync-live-dispatch       ── 1–5 min · dispatched · DEEP  (fuel events, status, ETA)
                                                                 │
                                                                 ▼
                                                          MariaDB
                                                                 │
                                              (Pusher broadcasts on write — IN PROGRESS)
                                                                 ▼
                                                          Browser polling (60s, current)
```

### Polling cadence

| Window | Lane | Cadence | API cost/day |
|---|---|---|---|
| 06:00–07:59 (quarry queue) | `sync-live-dispatch --cadence=1` | 1 min, dispatched only | ~600 detail calls + 120 bulk |
| 05:00 + 08:00–22:00 | `sync-live-dispatch --cadence=2` | 2 min, dispatched only | ~3600 detail calls |
| 23:00 + 00:00–04:00 | `sync-live-dispatch --cadence=5` | 5 min, dispatched only | ~700 detail calls |
| 05:00–22:00 | `sync-fleet-positions` | 2 min, all active | ~510 bulk calls |
| Every 30 min | `sync-kilometers` | 30 min, fleet-wide deep | ~190 detail calls |

All scheduled in `bootstrap/app.php` and mirrored in `app/Http/Controllers/CronController.php`
(Infomaniak inline scheduler — no `proc_open`).

---

## Database — new tables

### `daily_dispatch_events`
Materialised timeline per dispatch. Type enum:
`queued_at_quarry · loading_started · loaded_and_left · refuel · fuel_loss ·
long_stop · off_route · border_crossed · arrived_client · unloaded · returning ·
arrived_base · offline · online · breakdown_suspected`.

Idempotency via unique `dedupe_key` (sha1 of dispatch_id|type|bucket where
bucket is place_id for place-anchored events, fuel_event_id for fuel events,
truck_stop_id for long_stop, half-hour bucket for off_route).

Migration: `database/migrations/2026_05_22_100000_create_daily_dispatch_events_table.php`
Model: `app/Models/DailyDispatchEvent.php`

### `daily_dispatches` — added columns
- `current_status` (varchar) — French label, see DailyDispatch::STATUS_LIVE_*
- `current_status_at` (timestamp)
- `current_place_id` (FK to places)
- `last_event_id` (FK to daily_dispatch_events)
- `eta_at` (timestamp)

Migration: `2026_05_22_100100_add_live_status_to_daily_dispatches.php`

### `expected_transport_tickets`
One row per GPS-observed quarry loading (`loaded_and_left` event at a
provider_site whose place has `provider_id`). Status: `expected · matched ·
missing · dismissed`. Reconciliation matches by `(truck_id, provider_id,
|provider_date - loaded_at| ≤ 2h)`. Closes the 34-vs-13 gap.

Migration: `2026_05_22_100200_create_expected_transport_tickets_table.php`
Model: `app/Models/ExpectedTransportTicket.php`

### `places` — extended
`Place::TYPE_BORDER_POST = 'border_post'` added. No schema change (the type
column is `string(20)`). To use for border-crossing detection, seed a Place
row with type `border_post` at the Rosso ferry coordinates.

### Permissions
`live-fleet-view` permission granted to Logistics Responsible, Admin, Super
Admin, HSE Agent. Migration: `2026_05_22_100300_seed_live_fleet_permission.php`

---

## Services & status derivation

### `DispatchStatusResolver` (`app/Services/DispatchStatusResolver.php`)
Pure function. Inputs: dispatch + latest snapshot + recent events.
Output: one of the French labels (FILE_CARRIERE, CHARGEMENT, EN_ROUTE,
RETOUR, CHEZ_CLIENT, RAVITAILLEMENT, PASSAGE_FRONTIERE, A_LA_BASE,
ARRET_LONG, ARRET, OFFLINE, TERMINE).

Decision tree:
1. No telemetry OR device unseen > 15 min → `OFFLINE`
2. `arrived_base` in events → `TERMINE`
3. Inside a Place geofence → label derived from `Place::type` + recent events
4. Moving (speed ≥ 5 km/h, ignition on) → `EN_ROUTE` or `RETOUR` based on
   last `arrived_client` vs `loaded_and_left`
5. Stationary, no place: `ARRET_LONG` (> 45 min) or `ARRET`

### `DispatchEtaEstimator` (`app/Services/DispatchEtaEstimator.php`)
Median minutes from `trip_segments` for the (origin_place_id,
destination_type) leg, last 90 days, linked-only. Fallback: 540 min (laden
outbound) / 420 min (empty return). Cached 6h per leg pair.

### `DailyDispatchEventDeriver` (`app/Services/DailyDispatchEventDeriver.php`)
Reads outputs of existing detectors (`StopDetectorService`,
`PlaceClassifierService`, `FuelEventDetectorService`) and writes
idempotent timeline rows. Also opens `ExpectedTransportTicket` rows on
`loaded_and_left` at a provider site with a provider_id.

### `TicketReconciliationService` (`app/Services/TicketReconciliationService.php`)
Match expected → TransportTracking by (truck, provider, ±2h window). Mark
missing after deadline.

---

## Sync pipeline — `FleetiSyncService`

### `syncKilometers()` — unchanged
Existing 30-min fleet-wide deep pass.

### `syncLive(?$customerRef, Collection $trucks)` — new
Fast lane for dispatched trucks only. Per truck:
- `Cache::lock("fleeti:live:truck:{$id}", 60)`
- One bulk `fetchAssets` + per-truck `fetchAssetById` (for fuel sensor)
- `telemetrySnapshotService->record()`
- `fuelEventDetector->analyze()`
- `stopDetectorService->extendForTruck()` + `placeClassifierService->classify()`
- `unauthorizedStopDetector->inspect()`
- `dailyDispatchEventDeriver->derive()` + `dispatchStatusResolver->resolve()`
- Write `current_status / current_status_at / current_place_id / last_event_id / eta_at` on the dispatch
- Skips `KilometerService::applyExternalOdometerReading` and `EngineHoursService::applyExternalReading` (kept in 30-min lane)

### `syncFleetPositions(?$customerRef, Collection $allActive, Collection $dispatchedIds)` — new
Fleet-wide LIGHT pass. ONE bulk `/v1/Asset/Search` call. For each non-dispatched
truck (dispatched are owned by syncLive):
- `Cache::lock("fleeti:positions:truck:{$id}", 30)`
- Light snapshot + cache update (no per-asset detail call → no fuel data)
- Stops + place classification

This is what keeps `/logistics/fleet-map` fresh for the trucks NOT on
today's dispatch.

### `TruckRepository::getTrucksOnDispatchToday(int $windowHours = 18)` — new
Returns Trucks where:
- on today's `daily_dispatches` and `notification_status ∈ {sent,delivered,read}`
- OR on yesterday's dispatch and `current_status != TERMINE`
- AND `fleeti_asset_id IS NOT NULL`
- AND `fleeti_device_last_seen_at >= now() - windowHours`

---

## UI

- `/logistics/live` → `app/Http/Controllers/LiveFleetController.php` + `resources/js/pages/logistics/LiveFleet.tsx`
- `/reports/ticket-gap` → `app/Http/Controllers/TicketGapController.php` + `resources/js/pages/reports/TicketGap.tsx`
- Routes: `routes/web/live_fleet_route.php` (auto-loaded by `bootstrap/app.php`)
- Components: `resources/js/components/live-fleet/{StatusBadge,EventFeed,EtaCell}.tsx`
- French operational labels in `StatusBadge.tsx`

CSS fix: Leaflet z-index isolated below navbar (`.leaflet-container { isolation: isolate; z-index: 0 }`) in `resources/css/app.css`.

---

## Pusher / WebSocket layer — IN PROGRESS

### Decision
Real-time UX without a persistent worker (Infomaniak constraint) → hosted
Pusher (free tier). 200k msg/day cap; our scale ≈ 60k max.

### What's done
- `composer require pusher/pusher-php-server` (^7.2)
- `npm install laravel-echo pusher-js`
- `.env` populated (creds NOT in this doc — see `.env` only):
  - `BROADCAST_DRIVER=pusher`
  - `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER=eu`
  - `VITE_PUSHER_APP_KEY` and `VITE_PUSHER_APP_CLUSTER` already mirrored from PUSHER_* in `.env`
- Event class: `app/Events/TruckPositionUpdated.php` (implements `ShouldBroadcast`)
  - Public channel: `fleet.live`
  - Event name: `truck.position.updated`
  - Payload: truck id, matricule, lat/lng, heading, speed, movement_status, ignition_on, fuel_level, device_last_seen_at, last_sync
- `TelemetrySnapshotService::record()` now dispatches `TruckPositionUpdated::dispatch($truck->fresh())` after the cache write (only when latitude+longitude present, wrapped in try/catch — best-effort, never blocks the sync)

### What's left
1. **Frontend Echo bootstrap.** Create `resources/js/echo.ts` that initialises
   Laravel Echo with the Pusher driver from `import.meta.env.VITE_PUSHER_APP_KEY` /
   `VITE_PUSHER_APP_CLUSTER`. Import it from `resources/js/app.tsx` so it
   loads on every page.
2. **Subscribe `FleetMap.tsx`.** Convert `trucks` from a static prop to
   `useState`. On mount: `Echo.channel('fleet.live').listen('.truck.position.updated', e => updateTruckById(e.truck_id, e))`.
   The leading dot in `.truck.position.updated` is required for custom
   `broadcastAs()` event names.
3. **Subscribe `LiveFleet.tsx`.** Same channel + listener. Keep `usePolling`
   at 120s as a fallback for safety net (in case Echo disconnects).
4. **Test.** Open `/logistics/fleet-map` in a browser, run
   `php artisan fleeti:sync-fleet-positions` from a terminal, confirm a
   marker moves without a page reload.
5. **Rotate the Pusher secret.** It was pasted in chat during setup — change
   it from Pusher dashboard → Channels → app → App Keys → Roll secret, then
   update `.env`.

### Notes for frontend wiring (when resuming)

```ts
// resources/js/echo.ts (to create)
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

(window as any).Pusher = Pusher;

export const echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: true,
});
```

```ts
// in a page that wants live updates
import { echo } from '@/echo';
import { useEffect, useState } from 'react';

useEffect(() => {
  const channel = echo.channel('fleet.live');
  channel.listen('.truck.position.updated', (e: TruckUpdate) => {
    setTrucks(prev => prev.map(t => t.id === e.truck_id ? { ...t, ...e } : t));
  });
  return () => { echo.leave('fleet.live'); };
}, []);
```

---

## Verification commands

```bash
# Migrations dry-run
php artisan migrate --pretend --force

# Lint everything
php artisan about
php artisan schedule:list
php artisan route:list --path=logistics/live

# Smoke-test the commands
php artisan fleeti:sync-fleet-positions
php artisan fleeti:sync-live-dispatch --cadence=2
php artisan logistics:reconcile-expected-tickets
```

When Pusher front-end is wired:
1. `npm run dev` running
2. Open `/logistics/fleet-map` in two browser tabs
3. Run `php artisan fleeti:sync-fleet-positions` from terminal
4. Both tabs' markers should update within ~1 second without reload

---

## Files touched (master list)

### New PHP
- `app/Console/Commands/SyncFleetiLiveDispatch.php`
- `app/Console/Commands/SyncFleetiFleetPositions.php`
- `app/Console/Commands/ReconcileExpectedTickets.php`
- `app/Events/TruckPositionUpdated.php`
- `app/Http/Controllers/LiveFleetController.php`
- `app/Http/Controllers/TicketGapController.php`
- `app/Models/DailyDispatchEvent.php`
- `app/Models/ExpectedTransportTicket.php`
- `app/Services/DailyDispatchEventDeriver.php`
- `app/Services/DispatchStatusResolver.php`
- `app/Services/DispatchEtaEstimator.php`
- `app/Services/TicketReconciliationService.php`

### Modified PHP
- `app/Models/DailyDispatch.php` (live status constants, casts, relations)
- `app/Models/Place.php` (TYPE_BORDER_POST)
- `app/Models/TransportTracking.php` (expectedTicket relation)
- `app/Repositories/TruckRepository.php` (getTrucksOnDispatchToday)
- `app/Services/FleetiSyncService.php` (syncLive + syncFleetPositions)
- `app/Services/TelemetrySnapshotService.php` (TruckPositionUpdated dispatch)
- `app/Http/Controllers/CronController.php` (4 new schedule entries + JOBS slugs)
- `bootstrap/app.php` (5 new schedule entries)

### New migrations
- `2026_05_22_100000_create_daily_dispatch_events_table.php`
- `2026_05_22_100100_add_live_status_to_daily_dispatches.php`
- `2026_05_22_100200_create_expected_transport_tickets_table.php`
- `2026_05_22_100300_seed_live_fleet_permission.php`

### New routes
- `routes/web/live_fleet_route.php` (logistics.live.*, reports.ticket_gap.*)

### New frontend
- `resources/js/pages/logistics/LiveFleet.tsx`
- `resources/js/pages/reports/TicketGap.tsx`
- `resources/js/components/live-fleet/StatusBadge.tsx`
- `resources/js/components/live-fleet/EventFeed.tsx`
- `resources/js/components/live-fleet/EtaCell.tsx`

### Modified frontend
- `resources/css/app.css` (Leaflet z-index fix)

### Env / config
- `.env` (BROADCAST_DRIVER, PUSHER_* values)
- `composer.json` (pusher-php-server)
- `package.json` (laravel-echo, pusher-js)
