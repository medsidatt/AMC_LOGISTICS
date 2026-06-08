<?php

namespace App\Services;

use App\Models\DailyDispatch;
use App\Models\DailyDispatchEvent;
use App\Models\Place;
use App\Models\TruckStop;
use App\Models\TruckTelemetrySnapshot;
use Illuminate\Support\Collection;

/**
 * Pure derivation of a daily dispatch's live operational status from the
 * latest telemetry snapshot, the place the truck is currently inside (if any),
 * and the dispatch's recent events. Results in one of the French labels
 * defined on DailyDispatch::STATUS_LIVE_*.
 *
 * Stateless: never writes to the dispatch. Callers persist the returned label.
 */
class DispatchStatusResolver
{
    public function __construct(private readonly GeoService $geoService)
    {
    }

    /**
     * @param  DailyDispatchEvent[]|Collection  $recentEvents  Events relevant to this dispatch (today)
     */
    public function resolve(
        DailyDispatch $dispatch,
        ?TruckTelemetrySnapshot $snapshot,
        Collection $recentEvents
    ): array {
        // No telemetry at all → OFFLINE
        if (! $snapshot) {
            return ['status' => DailyDispatch::STATUS_LIVE_OFFLINE, 'place' => null];
        }

        $lastSeen = $snapshot->device_last_seen_at ?? $snapshot->recorded_at ?? $snapshot->synced_at;
        if ($lastSeen && abs(now()->diffInMinutes($lastSeen, false)) > 15) {
            return ['status' => DailyDispatch::STATUS_LIVE_OFFLINE, 'place' => null];
        }

        // Once arrived_base is observed, the round trip is closed
        if ($this->hasEvent($recentEvents, DailyDispatchEvent::TYPE_ARRIVED_BASE)) {
            return ['status' => DailyDispatch::STATUS_LIVE_TERMINE, 'place' => null];
        }

        $place = null;
        if ($snapshot->latitude !== null && $snapshot->longitude !== null) {
            $place = $this->geoService->nearestCoveringPlace(
                (float) $snapshot->latitude,
                (float) $snapshot->longitude
            );
        }

        $moving = ($snapshot->speed_kmh !== null && (float) $snapshot->speed_kmh >= 5.0)
            && $snapshot->ignition_on === true;

        if ($place) {
            switch ($place->type) {
                case Place::TYPE_PROVIDER_SITE:
                    if ($this->hasEvent($recentEvents, DailyDispatchEvent::TYPE_LOADED_AND_LEFT, $place->id)) {
                        // Already loaded and left earlier today; if back here it's a new cycle
                        return ['status' => DailyDispatch::STATUS_LIVE_EN_ROUTE, 'place' => $place];
                    }
                    if ($this->hasEvent($recentEvents, DailyDispatchEvent::TYPE_LOADING_STARTED, $place->id)) {
                        return ['status' => DailyDispatch::STATUS_LIVE_CHARGEMENT, 'place' => $place];
                    }
                    return ['status' => DailyDispatch::STATUS_LIVE_FILE_CARRIERE, 'place' => $place];

                case Place::TYPE_CLIENT_SITE:
                    if ($this->hasEvent($recentEvents, DailyDispatchEvent::TYPE_UNLOADED, $place->id)) {
                        return ['status' => DailyDispatch::STATUS_LIVE_RETOUR, 'place' => $place];
                    }
                    return ['status' => DailyDispatch::STATUS_LIVE_CHEZ_CLIENT, 'place' => $place];

                case Place::TYPE_FUEL_STATION:
                    if (! $moving) {
                        return ['status' => DailyDispatch::STATUS_LIVE_RAVITAILLEMENT, 'place' => $place];
                    }
                    break; // moving through a fuel station — treat as en route below

                case Place::TYPE_BORDER_POST:
                    return ['status' => DailyDispatch::STATUS_LIVE_PASSAGE_FRONTIERE, 'place' => $place];

                case Place::TYPE_BASE:
                    return ['status' => DailyDispatch::STATUS_LIVE_A_LA_BASE, 'place' => $place];
            }
        }

        if ($moving) {
            return [
                'status' => $this->inferEnRouteDirection($recentEvents),
                'place' => null,
            ];
        }

        // Stationary outside any known place
        $openStop = TruckStop::query()
            ->where('truck_id', $snapshot->truck_id)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();

        if ($openStop && $openStop->started_at && $openStop->started_at->diffInMinutes(now()) > 45) {
            return ['status' => DailyDispatch::STATUS_LIVE_ARRET_LONG, 'place' => null];
        }

        return ['status' => DailyDispatch::STATUS_LIVE_ARRET, 'place' => null];
    }

    private function inferEnRouteDirection(Collection $events): string
    {
        $lastClient = $events->where('type', DailyDispatchEvent::TYPE_ARRIVED_CLIENT)->sortByDesc('occurred_at')->first();
        $lastQuarry = $events->where('type', DailyDispatchEvent::TYPE_LOADED_AND_LEFT)->sortByDesc('occurred_at')->first();

        if ($lastClient && (! $lastQuarry || $lastClient->occurred_at > $lastQuarry->occurred_at)) {
            return DailyDispatch::STATUS_LIVE_RETOUR;
        }

        return DailyDispatch::STATUS_LIVE_EN_ROUTE;
    }

    private function hasEvent(Collection $events, string $type, ?int $placeId = null): bool
    {
        $filtered = $events->where('type', $type);
        if ($placeId !== null) {
            $filtered = $filtered->where('place_id', $placeId);
        }
        return $filtered->isNotEmpty();
    }
}
