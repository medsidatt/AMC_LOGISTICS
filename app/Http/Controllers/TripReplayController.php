<?php

namespace App\Http\Controllers;

use App\Models\TheftIncident;
use App\Models\TransportTracking;
use App\Models\TripSegment;
use App\Models\TruckStop;
use App\Models\TruckTelemetrySnapshot;
use Illuminate\Http\JsonResponse;

class TripReplayController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Returns a compact JSON payload for the trip-replay UI:
     *  - snapshot trail (lat/lng/speed/fuel/recorded_at), pruned to at most
     *    1 point every N seconds to keep the payload small
     *  - stops in the window with their place classification
     *  - theft incidents linked to the segment
     */
    public function data(TransportTracking $transportTracking): JsonResponse
    {
        $segment = TripSegment::query()
            ->where('transport_tracking_id', $transportTracking->id)
            ->with(['originPlace', 'destinationPlace'])
            ->first();

        if (! $segment) {
            return response()->json([
                'segment' => null,
                'trail' => [],
                'stops' => [],
                'incidents' => [],
            ]);
        }

        // Snapshot trail — we always fetch by time window even if start/end
        // snapshot IDs are set, to cover retroactive edits.
        $snapshots = TruckTelemetrySnapshot::query()
            ->where('truck_id', $segment->truck_id)
            ->whereBetween('recorded_at', [$segment->started_at, $segment->ended_at])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('recorded_at')
            ->get([
                'id', 'recorded_at', 'latitude', 'longitude',
                'speed_kmh', 'fuel_litres', 'odometer_km', 'movement_status', 'ignition_on',
            ]);

        // Prune: keep at most 1 point per 60 seconds so the payload is small
        // even for long trips. For a 12h trip at 30s ticks that's ~720 points.
        $pruned = [];
        $lastKept = null;
        foreach ($snapshots as $s) {
            $t = $s->recorded_at?->timestamp ?? 0;
            if ($lastKept === null || ($t - $lastKept) >= 60) {
                $pruned[] = [
                    'id' => $s->id,
                    'recorded_at' => $s->recorded_at?->toIso8601String(),
                    'latitude' => (float) $s->latitude,
                    'longitude' => (float) $s->longitude,
                    'speed_kmh' => $s->speed_kmh !== null ? (float) $s->speed_kmh : null,
                    'fuel_litres' => $s->fuel_litres !== null ? (float) $s->fuel_litres : null,
                    'odometer_km' => $s->odometer_km !== null ? (float) $s->odometer_km : null,
                    'movement_status' => $s->movement_status,
                    'ignition_on' => $s->ignition_on,
                ];
                $lastKept = $t;
            }
        }

        $stops = TruckStop::query()
            ->with('place:id,name,type')
            ->where('truck_id', $segment->truck_id)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$segment->started_at, $segment->ended_at])
            ->orderBy('started_at')
            ->get()
            ->map(fn (TruckStop $s) => [
                'id' => $s->id,
                'started_at' => $s->started_at?->format('d/m/Y H:i'),
                'ended_at' => $s->ended_at?->format('d/m/Y H:i'),
                'duration_minutes' => (int) round(($s->duration_seconds ?? 0) / 60),
                'latitude' => (float) $s->latitude,
                'longitude' => (float) $s->longitude,
                'classification' => $s->classification,
                'place' => $s->place ? ['id' => $s->place->id, 'name' => $s->place->name, 'type' => $s->place->type] : null,
                'fuel_delta_litres' => $s->fuelDeltaLitres(),
            ])
            ->values()
            ->all();

        $incidents = TheftIncident::query()
            ->where('trip_segment_id', $segment->id)
            ->orderByDesc('detected_at')
            ->get()
            ->map(fn (TheftIncident $i) => [
                'id' => $i->id,
                'type' => $i->type,
                'severity' => $i->severity,
                'status' => $i->status,
                'title' => $i->title,
                'detected_at' => $i->detected_at?->format('d/m/Y H:i'),
                'latitude' => $i->latitude,
                'longitude' => $i->longitude,
            ])
            ->values()
            ->all();

        return response()->json([
            'segment' => [
                'id' => $segment->id,
                'started_at' => $segment->started_at?->format('d/m/Y H:i'),
                'ended_at' => $segment->ended_at?->format('d/m/Y H:i'),
                'distance_km' => $segment->distance_km,
                'fuel_consumed_litres' => $segment->fuel_consumed_litres,
                'stop_count' => $segment->stop_count,
                'unknown_stop_count' => $segment->unknown_stop_count,
                'origin_place' => $segment->originPlace?->only(['id', 'name', 'type']),
                'destination_place' => $segment->destinationPlace?->only(['id', 'name', 'type']),
            ],
            'trail' => $pruned,
            'stops' => $stops,
            'incidents' => $incidents,
        ]);
    }
}
