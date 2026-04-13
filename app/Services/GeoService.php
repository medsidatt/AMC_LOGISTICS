<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pure geospatial helpers.
 *
 * No framework state; safe to unit-test. All distances are in metres unless
 * a method explicitly says km.
 */
class GeoService
{
    /** Earth mean radius in metres. */
    private const EARTH_RADIUS_M = 6371000.0;

    /**
     * Great-circle distance between two (lat, lng) pairs, in kilometres.
     */
    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return $this->haversineMetres($lat1, $lng1, $lat2, $lng2) / 1000.0;
    }

    /**
     * Great-circle distance between two (lat, lng) pairs, in metres.
     */
    public function haversineMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * (sin($deltaLng / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * True if (lat, lng) falls within the place's geofence radius.
     */
    public function isWithinRadius(float $lat, float $lng, Place $place): bool
    {
        $distance = $this->haversineMetres(
            $lat,
            $lng,
            (float) $place->latitude,
            (float) $place->longitude
        );

        return $distance <= (float) ($place->radius_m ?? 0);
    }

    /**
     * Find the closest ACTIVE place to the given coordinate.
     *
     * Applies a cheap lat/lng bounding box first, then does the haversine
     * on the filtered set — avoids a full-table scan when we have thousands
     * of places. Returns null if nothing is within $maxKm.
     *
     * @param  string|null  $type  Optional place type filter (base/provider_site/…).
     */
    public function nearestPlace(
        float $lat,
        float $lng,
        ?string $type = null,
        float $maxKm = 5.0
    ): ?Place {
        // A latitude degree is ~111 km. A longitude degree is 111 km * cos(lat).
        $latDelta = $maxKm / 111.0;
        $cosLat = max(0.00001, cos(deg2rad($lat)));
        $lngDelta = $maxKm / (111.0 * $cosLat);

        $query = Place::query()
            ->active()
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);

        if ($type !== null) {
            $query->ofType($type);
        }

        /** @var Collection<int, Place> $candidates */
        $candidates = $query->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        $best = null;
        $bestDistance = PHP_FLOAT_MAX;
        foreach ($candidates as $place) {
            $distance = $this->haversineMetres(
                $lat,
                $lng,
                (float) $place->latitude,
                (float) $place->longitude
            );
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $place;
            }
        }

        // Respect the caller's max distance (convert to metres)
        if ($bestDistance > $maxKm * 1000.0) {
            return null;
        }

        return $best;
    }

    /**
     * Find the closest ACTIVE place whose own radius COVERS the point.
     * Used by PlaceClassifierService: a stop is only classified as "at a known
     * place" if it falls inside that place's geofence.
     */
    public function nearestCoveringPlace(float $lat, float $lng): ?Place
    {
        // Broad candidate set: largest sensible radius = 5 km.
        $nearest = $this->nearestPlace($lat, $lng, null, 5.0);
        if ($nearest && $this->isWithinRadius($lat, $lng, $nearest)) {
            return $nearest;
        }

        return null;
    }

    /**
     * Midpoint of two (lat, lng) pairs. Used by the hub clustering command.
     */
    public function midpoint(float $lat1, float $lng1, float $lat2, float $lng2): array
    {
        return [
            'latitude' => round(($lat1 + $lat2) / 2, 7),
            'longitude' => round(($lng1 + $lng2) / 2, 7),
        ];
    }
}
