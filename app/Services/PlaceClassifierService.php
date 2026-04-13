<?php

namespace App\Services;

use App\Models\TruckStop;

/**
 * Classifies a TruckStop against the `places` geofence table.
 *
 * Outcome:
 *  - If the stop's coordinates fall inside some active place's radius → sets
 *    place_id and maps the classification (e.g. 'known_base' for type=base).
 *  - Otherwise classification is set to 'unknown' so downstream detectors
 *    can escalate it.
 */
class PlaceClassifierService
{
    public function __construct(private readonly GeoService $geoService)
    {
    }

    public function classify(TruckStop $stop): TruckStop
    {
        $place = $this->geoService->nearestCoveringPlace(
            (float) $stop->latitude,
            (float) $stop->longitude
        );

        if ($place) {
            $stop->update([
                'place_id' => $place->id,
                'classification' => $place->classificationForStop(),
            ]);
            return $stop->fresh();
        }

        $stop->update([
            'place_id' => null,
            'classification' => TruckStop::CLASS_UNKNOWN,
        ]);

        return $stop->fresh();
    }
}
