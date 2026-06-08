<?php

namespace App\Services;

use App\Models\DailyDispatch;
use App\Models\DailyDispatchEvent;
use App\Models\ExpectedTransportTicket;
use App\Models\FuelEvent;
use App\Models\Place;
use App\Models\Truck;
use App\Models\TruckStop;
use App\Models\TruckTelemetrySnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Materialises the operational event timeline for a single dispatch tick.
 *
 * Reads from the existing detectors' outputs (closed TruckStops, FuelEvents,
 * latest telemetry snapshot) and writes idempotent DailyDispatchEvent rows
 * keyed by a stable dedupe_key. Also opens ExpectedTransportTicket rows when
 * a quarry loading is observed (under-ticketing reconciliation).
 */
class DailyDispatchEventDeriver
{
    public function __construct(private readonly GeoService $geoService)
    {
    }

    /**
     * @param  TruckStop[]  $closedStops   Stops that were just closed by StopDetectorService.
     * @param  FuelEvent[]  $newFuelEvents Fuel events created during this tick.
     * @return DailyDispatchEvent[]
     */
    public function derive(
        DailyDispatch $dispatch,
        Truck $truck,
        ?TruckTelemetrySnapshot $snapshot,
        array $closedStops,
        array $newFuelEvents
    ): array {
        $created = [];

        // 1. Closed stops at known places → place-anchored lifecycle events
        foreach ($closedStops as $stop) {
            $stopEvents = $this->eventsFromClosedStop($dispatch, $truck, $stop);
            $created = array_merge($created, $stopEvents);
        }

        // 2. Fuel events → refuel / fuel_loss
        foreach ($newFuelEvents as $fuelEvent) {
            $event = $this->eventFromFuelEvent($dispatch, $truck, $fuelEvent);
            if ($event) {
                $created[] = $event;
            }
        }

        // 3. Live "currently inside a geofence" events from the latest snapshot
        if ($snapshot) {
            $liveEvents = $this->liveSnapshotEvents($dispatch, $truck, $snapshot);
            $created = array_merge($created, $liveEvents);
        }

        // 4. Long-stop open detection
        $longStopEvent = $this->detectLongStop($dispatch, $truck);
        if ($longStopEvent) {
            $created[] = $longStopEvent;
        }

        // 5. Online/offline transitions
        $linkEvent = $this->detectOnlineOfflineTransition($dispatch, $truck, $snapshot);
        if ($linkEvent) {
            $created[] = $linkEvent;
        }

        return $created;
    }

    private function eventsFromClosedStop(DailyDispatch $dispatch, Truck $truck, TruckStop $stop): array
    {
        if (! $stop->place_id || ! $stop->place) {
            return [];
        }

        $place = $stop->place;
        $events = [];

        switch ($place->type) {
            case Place::TYPE_PROVIDER_SITE:
                $startedAt = $stop->started_at;
                $endedAt = $stop->ended_at;

                if ($startedAt) {
                    $event = $this->insertEvent($dispatch, $truck, [
                        'type' => DailyDispatchEvent::TYPE_QUEUED_AT_QUARRY,
                        'occurred_at' => $startedAt,
                        'place_id' => $place->id,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                        'snapshot_id' => $stop->start_snapshot_id,
                        'payload' => ['place_name' => $place->name],
                    ], "place:{$place->id}:stop:{$stop->id}:queued");
                    if ($event) {
                        $events[] = $event;
                    }
                }

                if ($endedAt) {
                    $event = $this->insertEvent($dispatch, $truck, [
                        'type' => DailyDispatchEvent::TYPE_LOADED_AND_LEFT,
                        'occurred_at' => $endedAt,
                        'place_id' => $place->id,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                        'snapshot_id' => $stop->end_snapshot_id,
                        'payload' => [
                            'place_name' => $place->name,
                            'duration_min' => $stop->duration_seconds
                                ? (int) round($stop->duration_seconds / 60)
                                : null,
                        ],
                    ], "place:{$place->id}:stop:{$stop->id}:left");
                    if ($event) {
                        $events[] = $event;
                        $this->ensureExpectedTicket($dispatch, $truck, $place, $stop);
                    }
                }
                break;

            case Place::TYPE_CLIENT_SITE:
                if ($stop->started_at) {
                    $arrived = $this->insertEvent($dispatch, $truck, [
                        'type' => DailyDispatchEvent::TYPE_ARRIVED_CLIENT,
                        'occurred_at' => $stop->started_at,
                        'place_id' => $place->id,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                        'snapshot_id' => $stop->start_snapshot_id,
                        'payload' => ['place_name' => $place->name],
                    ], "place:{$place->id}:stop:{$stop->id}:arrived");
                    if ($arrived) {
                        $events[] = $arrived;
                    }
                }
                if ($stop->ended_at) {
                    $unloaded = $this->insertEvent($dispatch, $truck, [
                        'type' => DailyDispatchEvent::TYPE_UNLOADED,
                        'occurred_at' => $stop->ended_at,
                        'place_id' => $place->id,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                        'snapshot_id' => $stop->end_snapshot_id,
                        'payload' => [
                            'place_name' => $place->name,
                            'duration_min' => $stop->duration_seconds
                                ? (int) round($stop->duration_seconds / 60)
                                : null,
                        ],
                    ], "place:{$place->id}:stop:{$stop->id}:unloaded");
                    if ($unloaded) {
                        $events[] = $unloaded;
                    }
                }
                break;

            case Place::TYPE_BASE:
                if ($stop->started_at) {
                    $event = $this->insertEvent($dispatch, $truck, [
                        'type' => DailyDispatchEvent::TYPE_ARRIVED_BASE,
                        'occurred_at' => $stop->started_at,
                        'place_id' => $place->id,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                        'snapshot_id' => $stop->start_snapshot_id,
                        'payload' => ['place_name' => $place->name],
                    ], "place:{$place->id}:stop:{$stop->id}:arrived_base");
                    if ($event) {
                        $events[] = $event;
                    }
                }
                break;

            case Place::TYPE_BORDER_POST:
                if ($stop->started_at) {
                    $event = $this->insertEvent($dispatch, $truck, [
                        'type' => DailyDispatchEvent::TYPE_BORDER_CROSSED,
                        'occurred_at' => $stop->started_at,
                        'place_id' => $place->id,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                        'snapshot_id' => $stop->start_snapshot_id,
                        'payload' => ['place_name' => $place->name],
                    ], "place:{$place->id}:stop:{$stop->id}:border");
                    if ($event) {
                        $events[] = $event;
                    }
                }
                break;
        }

        return $events;
    }

    private function eventFromFuelEvent(DailyDispatch $dispatch, Truck $truck, FuelEvent $fuelEvent): ?DailyDispatchEvent
    {
        $type = match ($fuelEvent->event_type) {
            FuelEvent::TYPE_REFILL => DailyDispatchEvent::TYPE_REFUEL,
            FuelEvent::TYPE_DROP, FuelEvent::TYPE_THEFT_SUSPECTED => DailyDispatchEvent::TYPE_FUEL_LOSS,
            default => null,
        };

        if ($type === null) {
            return null;
        }

        return $this->insertEvent($dispatch, $truck, [
            'type' => $type,
            'occurred_at' => $fuelEvent->detected_at ?? now(),
            'place_id' => null,
            'latitude' => $fuelEvent->latitude,
            'longitude' => $fuelEvent->longitude,
            'snapshot_id' => $fuelEvent->snapshot_after_id,
            'payload' => [
                'litres_delta' => (float) $fuelEvent->litres_delta,
                'litres_before' => (float) $fuelEvent->litres_before,
                'litres_after' => (float) $fuelEvent->litres_after,
                'fuel_event_id' => $fuelEvent->id,
            ],
        ], "fuel_event:{$fuelEvent->id}");
    }

    private function liveSnapshotEvents(DailyDispatch $dispatch, Truck $truck, TruckTelemetrySnapshot $snapshot): array
    {
        if ($snapshot->latitude === null || $snapshot->longitude === null) {
            return [];
        }

        $place = $this->geoService->nearestCoveringPlace(
            (float) $snapshot->latitude,
            (float) $snapshot->longitude
        );

        if (! $place) {
            return [];
        }

        // On entering a quarry whose loading is in progress (truck stationary
        // but ignition on, or "idle" movement_status), record loading_started.
        if ($place->type === Place::TYPE_PROVIDER_SITE) {
            $speed = $snapshot->speed_kmh !== null ? (float) $snapshot->speed_kmh : 0.0;
            $hour = $snapshot->recorded_at ? (int) $snapshot->recorded_at->format('H') : (int) now()->format('H');
            $isLoadingWindow = $hour >= 6 && $hour <= 13;
            if ($isLoadingWindow && $speed < 1.0 && $snapshot->ignition_on === true) {
                $event = $this->insertEvent($dispatch, $truck, [
                    'type' => DailyDispatchEvent::TYPE_LOADING_STARTED,
                    'occurred_at' => $snapshot->recorded_at ?? now(),
                    'place_id' => $place->id,
                    'latitude' => $snapshot->latitude,
                    'longitude' => $snapshot->longitude,
                    'snapshot_id' => $snapshot->id,
                    'payload' => ['place_name' => $place->name],
                ], "place:{$place->id}:date:" . ($snapshot->recorded_at ?? now())->toDateString() . ":loading");

                return $event ? [$event] : [];
            }
        }

        return [];
    }

    private function detectLongStop(DailyDispatch $dispatch, Truck $truck): ?DailyDispatchEvent
    {
        $openStop = TruckStop::query()
            ->where('truck_id', $truck->id)
            ->whereNull('ended_at')
            ->whereNull('place_id')
            ->orderByDesc('started_at')
            ->first();

        if (! $openStop || ! $openStop->started_at) {
            return null;
        }
        if ($openStop->started_at->diffInMinutes(now()) <= 45) {
            return null;
        }

        $hour = (int) now()->format('H');
        if ($hour < 6 || $hour > 20) {
            return null;
        }

        return $this->insertEvent($dispatch, $truck, [
            'type' => DailyDispatchEvent::TYPE_LONG_STOP,
            'occurred_at' => $openStop->started_at,
            'place_id' => null,
            'latitude' => $openStop->latitude,
            'longitude' => $openStop->longitude,
            'snapshot_id' => $openStop->start_snapshot_id,
            'payload' => [
                'minutes' => (int) $openStop->started_at->diffInMinutes(now()),
                'truck_stop_id' => $openStop->id,
            ],
        ], "stop:{$openStop->id}:long");
    }

    private function detectOnlineOfflineTransition(
        DailyDispatch $dispatch,
        Truck $truck,
        ?TruckTelemetrySnapshot $snapshot
    ): ?DailyDispatchEvent {
        if (! $snapshot) {
            return null;
        }

        $lastSeen = $snapshot->device_last_seen_at ?? $snapshot->recorded_at;
        if (! $lastSeen) {
            return null;
        }

        $isOnline = abs(now()->diffInMinutes($lastSeen, false)) <= 15;
        $currentlyMarkedOffline = $dispatch->current_status === DailyDispatch::STATUS_LIVE_OFFLINE;

        if ($isOnline && $currentlyMarkedOffline) {
            return $this->insertEvent($dispatch, $truck, [
                'type' => DailyDispatchEvent::TYPE_ONLINE,
                'occurred_at' => now(),
                'place_id' => null,
                'latitude' => $snapshot->latitude,
                'longitude' => $snapshot->longitude,
                'snapshot_id' => $snapshot->id,
                'payload' => [],
            ], 'online:' . now()->format('YmdHi'));
        }

        if (! $isOnline && ! $currentlyMarkedOffline) {
            return $this->insertEvent($dispatch, $truck, [
                'type' => DailyDispatchEvent::TYPE_OFFLINE,
                'occurred_at' => $lastSeen,
                'place_id' => null,
                'latitude' => $snapshot->latitude,
                'longitude' => $snapshot->longitude,
                'snapshot_id' => $snapshot->id,
                'payload' => ['last_seen_minutes' => (int) abs(now()->diffInMinutes($lastSeen, false))],
            ], 'offline:' . $lastSeen->format('YmdHi'));
        }

        return null;
    }

    private function ensureExpectedTicket(DailyDispatch $dispatch, Truck $truck, Place $place, TruckStop $stop): void
    {
        if (! $place->provider_id) {
            return; // place not linked to a Provider — can't reconcile against TransportTracking
        }

        try {
            ExpectedTransportTicket::firstOrCreate(
                [
                    'daily_dispatch_id' => $dispatch->id,
                    'loaded_at' => $stop->ended_at ?? $stop->started_at ?? now(),
                ],
                [
                    'truck_id' => $truck->id,
                    'provider_id' => $place->provider_id,
                    'left_at' => $stop->ended_at,
                    // Give logistics until 24h later (next morning) to register the ticket
                    'deadline_at' => ($stop->ended_at ?? now())->copy()->addHours(24),
                    'status' => ExpectedTransportTicket::STATUS_EXPECTED,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to create expected transport ticket', [
                'dispatch_id' => $dispatch->id,
                'truck_id' => $truck->id,
                'place_id' => $place->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Insert a DailyDispatchEvent with stable dedupe_key — returns null on
     * unique-index collision (event already exists for this bucket).
     */
    private function insertEvent(DailyDispatch $dispatch, Truck $truck, array $attrs, string $bucket): ?DailyDispatchEvent
    {
        $type = $attrs['type'];
        $dedupeKey = DailyDispatchEvent::buildDedupeKey($dispatch->id, $type, $bucket);

        try {
            return DailyDispatchEvent::create(array_merge([
                'daily_dispatch_id' => $dispatch->id,
                'truck_id' => $truck->id,
                'driver_id' => $dispatch->driver_id,
                'source' => DailyDispatchEvent::SOURCE_GPS,
                'dedupe_key' => $dedupeKey,
            ], $attrs));
        } catch (QueryException $e) {
            // Likely a unique-index violation on dedupe_key (race or already
            // derived). Silently ignore — desired behaviour for idempotency.
            return null;
        } catch (\Throwable $e) {
            Log::warning('Failed to insert dispatch event', [
                'dispatch_id' => $dispatch->id,
                'type' => $type,
                'bucket' => $bucket,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
