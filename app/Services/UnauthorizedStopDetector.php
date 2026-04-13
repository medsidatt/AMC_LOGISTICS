<?php

namespace App\Services;

use App\Models\TheftIncident;
use App\Models\TripSegment;
use App\Models\TruckStop;

/**
 * Escalates a closed truck_stop to a theft_incident when:
 *  - its duration exceeds the configured threshold
 *  - it is classified as 'unknown' (nearest known place does NOT cover it)
 *  - it falls inside a trip_segment that is linked to a transport_tracking
 *    (so we only flag stops that occurred during an actual mission — not
 *    parking at the garage)
 */
class UnauthorizedStopDetector
{
    public function __construct(private readonly TheftIncidentService $theftIncidentService)
    {
    }

    public function inspect(TruckStop $stop): ?TheftIncident
    {
        if ($stop->ended_at === null) {
            return null;  // still open
        }

        if ($stop->classification !== TruckStop::CLASS_UNKNOWN) {
            return null;  // stop happened at a known place — fine
        }

        $minDuration = (int) config('maintenance.unauthorized_stop_min_duration_seconds', 1200);
        if (($stop->duration_seconds ?? 0) < $minDuration) {
            return null;  // too short
        }

        // Must fall inside a trip segment that belongs to a real transport.
        $segment = TripSegment::query()
            ->where('truck_id', $stop->truck_id)
            ->whereNotNull('transport_tracking_id')
            ->where('started_at', '<=', $stop->started_at)
            ->where('ended_at', '>=', $stop->started_at)
            ->orderByDesc('started_at')
            ->first();

        if (! $segment) {
            return null;  // stop happened outside any known mission
        }

        $durationMinutes = (int) round(($stop->duration_seconds ?? 0) / 60);

        return $this->theftIncidentService->open([
            'truck_id' => $stop->truck_id,
            'transport_tracking_id' => $segment->transport_tracking_id,
            'trip_segment_id' => $segment->id,
            'truck_stop_id' => $stop->id,
            'type' => TheftIncident::TYPE_UNAUTHORIZED_STOP,
            'severity' => $durationMinutes >= 60
                ? TheftIncident::SEVERITY_HIGH
                : TheftIncident::SEVERITY_MEDIUM,
            'detected_at' => $stop->ended_at,
            'latitude' => $stop->latitude,
            'longitude' => $stop->longitude,
            'title' => sprintf(
                'Arrêt non autorisé de %d min pendant la mission #%s',
                $durationMinutes,
                $segment->transportTracking?->reference ?? $segment->transport_tracking_id
            ),
            'evidence' => [
                'dedup_key' => 'unauthorized_stop:stop=' . $stop->id,
                'duration_seconds' => $stop->duration_seconds,
                'duration_minutes' => $durationMinutes,
                'fuel_litres_at_start' => $stop->fuel_litres_at_start,
                'fuel_litres_at_end' => $stop->fuel_litres_at_end,
                'fuel_delta_litres' => $stop->fuelDeltaLitres(),
                'ignition_was_off' => $stop->ignition_was_off,
                'started_at' => $stop->started_at?->toIso8601String(),
                'ended_at' => $stop->ended_at?->toIso8601String(),
            ],
        ]);
    }
}
