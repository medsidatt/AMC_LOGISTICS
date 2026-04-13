<?php

namespace App\Services;

use App\Models\Place;
use App\Models\TheftIncident;
use App\Models\TripSegment;

/**
 * Escalates a trip_segment to a theft_incident when the actual driven
 * distance is much larger than the straight-line distance between origin
 * and destination places.
 *
 * Signal: `actual_km > haversine(origin, destination) * factor`
 *
 * Only runs when both origin_place and destination_place are set AND the
 * segment is linked to a transport_tracking — we don't care about unknown
 * trips.
 */
class RouteDeviationDetector
{
    public function __construct(
        private readonly GeoService $geoService,
        private readonly TheftIncidentService $theftIncidentService
    ) {
    }

    public function inspect(TripSegment $segment): ?TheftIncident
    {
        if ($segment->transport_tracking_id === null) {
            return null;
        }
        if ($segment->origin_place_id === null || $segment->destination_place_id === null) {
            return null;
        }
        if ($segment->distance_km === null || (float) $segment->distance_km <= 0) {
            return null;
        }

        $origin = Place::find($segment->origin_place_id);
        $destination = Place::find($segment->destination_place_id);
        if (! $origin || ! $destination) {
            return null;
        }

        $straightLineKm = $this->geoService->haversineKm(
            (float) $origin->latitude,
            (float) $origin->longitude,
            (float) $destination->latitude,
            (float) $destination->longitude
        );
        if ($straightLineKm < 1.0) {
            return null;  // origin and destination are essentially the same place
        }

        $factor = (float) config('maintenance.route_deviation_factor', 1.6);
        $maxExpectedKm = $straightLineKm * $factor;

        if ((float) $segment->distance_km <= $maxExpectedKm) {
            return null;  // within tolerance
        }

        $excessKm = round((float) $segment->distance_km - $maxExpectedKm, 1);
        $severity = match (true) {
            $excessKm >= 100 => TheftIncident::SEVERITY_HIGH,
            $excessKm >= 30 => TheftIncident::SEVERITY_MEDIUM,
            default => TheftIncident::SEVERITY_LOW,
        };

        $segment->load('transportTracking');

        return $this->theftIncidentService->open([
            'truck_id' => $segment->truck_id,
            'transport_tracking_id' => $segment->transport_tracking_id,
            'trip_segment_id' => $segment->id,
            'type' => TheftIncident::TYPE_ROUTE_DEVIATION,
            'severity' => $severity,
            'detected_at' => $segment->ended_at ?? now(),
            'title' => sprintf(
                "Déviation d'itinéraire: %s km effectifs vs %s km attendus",
                number_format((float) $segment->distance_km, 0, ',', ' '),
                number_format($maxExpectedKm, 0, ',', ' ')
            ),
            'evidence' => [
                'dedup_key' => 'route_deviation:transport=' . $segment->transport_tracking_id,
                'actual_km' => (float) $segment->distance_km,
                'straight_line_km' => round($straightLineKm, 2),
                'max_expected_km' => round($maxExpectedKm, 2),
                'excess_km' => $excessKm,
                'factor' => $factor,
                'origin_place' => ['id' => $origin->id, 'name' => $origin->name],
                'destination_place' => ['id' => $destination->id, 'name' => $destination->name],
            ],
        ]);
    }
}
